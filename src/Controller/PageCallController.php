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
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotDetectorInterface;
use Zhortein\SeoTrackingBundle\Tracking\Entity\TrackingEntityAccessor;
use Zhortein\SeoTrackingBundle\Tracking\Factory\PageCallFactoryInterface;
use Zhortein\SeoTrackingBundle\Tracking\Factory\PageCallHitFactoryInterface;
use Zhortein\SeoTrackingBundle\Tracking\Grouping\PageCallGroupingKeyGeneratorInterface;
use Zhortein\SeoTrackingBundle\Tracking\Ip\IpAnonymizerInterface;
use Zhortein\SeoTrackingBundle\Tracking\Request\TrackingPayload;
use Zhortein\SeoTrackingBundle\Tracking\Request\TrackingPayloadFactory;

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
        private readonly BotDetectorInterface $botDetector,
        private readonly TrackingEntityAccessor $entityAccessor,
    ) {
    }

    #[Route('/page-call/track', name: 'page_call_track', methods: ['POST'])]
    public function track(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        try {
            $payload = $this->payloadFactory->fromRequest($request);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        $calledAt = new \DateTimeImmutable();
        $userAgent = $request->headers->get('User-Agent');
        $bot = $this->botDetector->isBot($userAgent);
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

        $dispatcher->dispatch(new PageCallTrackedEvent($pageCall, $hit));

        return new JsonResponse(['hitId' => $this->entityAccessor->getHitId($hit)]);
    }

    #[Route('/page-call/exit', name: 'page_call_exit', methods: ['POST'])]
    public function exit(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!is_array($data) || !isset($data['hitId']) || (!is_int($data['hitId']) && !is_string($data['hitId']))) {
            return new JsonResponse(['error' => 'Missing or invalid hitId'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $hit = $em->getRepository($this->pageCallHitClass)->find($data['hitId']);
        if (!$hit instanceof PageCallHitInterface) {
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
}
