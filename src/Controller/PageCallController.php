<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Event\PageCallExitEvent;
use Zhortein\SeoTrackingBundle\Event\PageCallTrackedEvent;
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassifierInterface;
use Zhortein\SeoTrackingBundle\Tracking\Consent\TrackingConsentCheckerInterface;
use Zhortein\SeoTrackingBundle\Tracking\Entity\TrackingEntityAccessor;
use Zhortein\SeoTrackingBundle\Tracking\Factory\PageCallFactoryInterface;
use Zhortein\SeoTrackingBundle\Tracking\Factory\PageCallHitFactoryInterface;
use Zhortein\SeoTrackingBundle\Tracking\Grouping\PageCallGroupingKeyGeneratorInterface;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventFactory;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReason;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReporterInterface;
use Zhortein\SeoTrackingBundle\Tracking\Ip\IpAnonymizerInterface;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingRateLimitDecision;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingRateLimiterInterface;
use Zhortein\SeoTrackingBundle\Tracking\Request\TrackingPayload;
use Zhortein\SeoTrackingBundle\Tracking\Request\TrackingPayloadFactory;
use Zhortein\SeoTrackingBundle\Tracking\Request\UnsupportedTrackingPayloadException;

class PageCallController extends AbstractController
{
    /**
     * @param class-string<PageCallInterface>    $pageCallClass
     * @param class-string<PageCallHitInterface> $pageCallHitClass
     */
    public function __construct(
        private readonly string $pageCallClass,
        private readonly string $pageCallHitClass,
        private readonly PageCallFactoryInterface $pageCallFactory,
        private readonly PageCallHitFactoryInterface $pageCallHitFactory,
        private readonly TrackingPayloadFactory $payloadFactory,
        private readonly PageCallGroupingKeyGeneratorInterface $groupingKeyGenerator,
        private readonly IpAnonymizerInterface $ipAnonymizer,
        private readonly BotClassifierInterface $botClassifier,
        private readonly TrackingEntityAccessor $entityAccessor,
        private readonly TrackingConsentCheckerInterface $consentChecker,
        private readonly TrackingRateLimiterInterface $rateLimiter,
        private readonly InvalidTrackingEventFactory $invalidEventFactory,
        private readonly InvalidTrackingEventReporterInterface $invalidEventReporter,
    ) {
    }

    #[Route('/page-call/track', name: 'page_call_track', methods: ['POST'])]
    public function track(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        $rateLimit = $this->rateLimiter->consume($request, TrackingEndpoint::CREATION);
        if (!$rateLimit->accepted) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CREATION, InvalidTrackingEventReason::RATE_LIMITED);

            return $this->rateLimitedResponse($rateLimit);
        }

        if (!$this->consentChecker->isGranted($request)) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CREATION, InvalidTrackingEventReason::CONSENT_DENIED);

            return new JsonResponse(['error' => 'Tracking consent is required'], JsonResponse::HTTP_FORBIDDEN);
        }

        try {
            $payload = $this->payloadFactory->fromRequest($request);
        } catch (\JsonException $exception) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CREATION, InvalidTrackingEventReason::MALFORMED_JSON);

            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (UnsupportedTrackingPayloadException $exception) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CREATION, InvalidTrackingEventReason::UNSUPPORTED_PAYLOAD);

            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $exception) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CREATION, InvalidTrackingEventReason::INVALID_PAYLOAD);

            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        $calledAt = new \DateTimeImmutable();
        $userAgent = $request->headers->get('User-Agent');
        $botClassification = $this->botClassifier->classify($userAgent);
        $bot = $botClassification->bot;
        $groupingKey = $this->groupingKeyGenerator->generate($payload->groupingUrl(), $payload->utm(), $bot);

        $pageCall = $this->findPageCall($em, $payload, $groupingKey, $bot);
        if (null === $pageCall) {
            $pageCall = $this->pageCallFactory->create();
            $this->entityAccessor->initializePageCall($pageCall, $payload, $groupingKey, $bot, $calledAt);
            $em->persist($pageCall);
        }

        $this->entityAccessor->registerCall($pageCall, \DateTime::createFromImmutable($calledAt));

        $hit = $this->pageCallHitFactory->create();
        $this->entityAccessor->initializeHit(
            $hit,
            $pageCall,
            $payload,
            $this->truncate($request->headers->get('referer'), 2048),
            $this->truncate($userAgent, 512),
            $this->ipAnonymizer->anonymize($request->getClientIp()),
            $bot,
            $calledAt,
        );

        if (null !== $payload->parentHitId) {
            $parentHit = $em->getRepository($this->pageCallHitClass)->find($payload->parentHitId);
            if ($parentHit instanceof PageCallHitInterface) {
                $this->entityAccessor->setParentHit($hit, $parentHit);
            }
        }

        $this->entityAccessor->updateDuration($hit);
        $em->persist($hit);

        try {
            $em->flush();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Database error'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $dispatcher->dispatch(new PageCallTrackedEvent($pageCall, $hit, $botClassification));

        return new JsonResponse(['hitId' => $this->entityAccessor->getHitId($hit)]);
    }

    #[Route('/page-call/exit', name: 'page_call_exit', methods: ['POST'])]
    public function exit(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        $rateLimit = $this->rateLimiter->consume($request, TrackingEndpoint::CLOSURE);
        if (!$rateLimit->accepted) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CLOSURE, InvalidTrackingEventReason::RATE_LIMITED);

            return $this->rateLimitedResponse($rateLimit);
        }

        $content = $request->getContent();
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CLOSURE, InvalidTrackingEventReason::MALFORMED_JSON);

            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!str_starts_with(ltrim($content), '{') || !is_array($data)) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CLOSURE, InvalidTrackingEventReason::UNSUPPORTED_PAYLOAD);

            return new JsonResponse(['error' => 'The JSON payload must be an object'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['hitId']) || (!is_int($data['hitId']) && !is_string($data['hitId']))) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CLOSURE, InvalidTrackingEventReason::INVALID_PAYLOAD);

            return new JsonResponse(['error' => 'Missing or invalid hitId'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $hit = $em->getRepository($this->pageCallHitClass)->find($data['hitId']);
        if (!$hit instanceof PageCallHitInterface) {
            $this->reportInvalidEvent($request, TrackingEndpoint::CLOSURE, InvalidTrackingEventReason::UNKNOWN_HIT);

            return new JsonResponse(['error' => 'Unknown hit'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($this->entityAccessor->isHitClosed($hit)) {
            return new JsonResponse(['status' => 'already_closed']);
        }

        $this->entityAccessor->closeHit($hit, new \DateTimeImmutable());
        $em->flush();

        $dispatcher->dispatch(new PageCallExitEvent($this->entityAccessor->getPageCall($hit), $hit));

        return new JsonResponse(['status' => 'ok']);
    }

    private function findPageCall(
        EntityManagerInterface $em,
        TrackingPayload $payload,
        string $groupingKey,
        bool $bot,
    ): ?PageCallInterface {
        $metadata = $em->getClassMetadata($this->pageCallClass);
        $criteria = $metadata->hasField('groupingKey')
            ? ['groupingKey' => $groupingKey]
            : [
                'url' => $payload->groupingUrl(),
                'campaign' => $payload->campaign,
                'medium' => $payload->medium,
                'source' => $payload->source,
                'term' => $payload->term,
                'content' => $payload->content,
                'bot' => $bot,
            ];

        return $em->getRepository($this->pageCallClass)->findOneBy($criteria);
    }

    private function truncate(?string $value, int $length): ?string
    {
        return null === $value ? null : mb_substr($value, 0, $length);
    }

    private function rateLimitedResponse(TrackingRateLimitDecision $decision): JsonResponse
    {
        $headers = [];
        if (null !== $decision->limit) {
            $headers['X-RateLimit-Limit'] = (string) $decision->limit;
        }
        if (null !== $decision->remainingTokens) {
            $headers['X-RateLimit-Remaining'] = (string) $decision->remainingTokens;
        }
        if (null !== $decision->retryAfter) {
            $headers['Retry-After'] = $decision->retryAfter->format(DATE_RFC7231);
        }

        return new JsonResponse(
            ['error' => 'Tracking rate limit exceeded'],
            JsonResponse::HTTP_TOO_MANY_REQUESTS,
            $headers,
        );
    }

    private function reportInvalidEvent(
        Request $request,
        TrackingEndpoint $endpoint,
        InvalidTrackingEventReason $reason,
    ): void {
        try {
            $this->invalidEventReporter->report(
                $this->invalidEventFactory->create($request, $endpoint, $reason),
            );
        } catch (\Throwable) {
            // Rejection responses must not expose or depend on observability failures.
        }
    }
}
