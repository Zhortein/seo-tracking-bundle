<?php

namespace Zhortein\SeoTrackingBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Event\PageCallTrackedEvent;

class PageCallController extends AbstractController
{
    private function isBot(string $userAgent): bool
    {
        return $userAgent && preg_match('/bot|crawl|slurp|spider/i', $userAgent);
    }

    #[Route('/page-call/track', name: 'page_call_track', methods: ['POST'])]
    public function track(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
        }

        if (empty($data['url'])) {
            return new JsonResponse(['error' => 'Missing URL'], 400);
        }

        $nowImmutable = new \DateTimeImmutable();
        $now = new \DateTime();

        $pageCallRepository = $em->getRepository(PageCall::class);
        $pageCallHitRepository = $em->getRepository(PageCallHit::class);
        $userAgent = $request->headers->get('User-Agent');
        $isBot = $this->isBot($userAgent);

        $pageCall = $pageCallRepository->findOneBy([
            'url' => $data['url'],
            'campaign' => $data['campaign'],
            'medium' => $data['medium'],
            'source' => $data['source'],
            'term' => $data['term'],
            'content' => $data['content'],
            'bot' => $isBot,
        ]);

        if (!$pageCall) {
            $pageCall = new PageCall();
            $pageCall
                ->setUrl($data['url'])
                ->setRoute($data['route'] ?? null)
                ->setRouteArgs($data['routeArgs'] ?? null)
                ->setCampaign($data['campaign'] ?? null)
                ->setMedium($data['medium'] ?? null)
                ->setSource($data['source'] ?? null)
                ->setTerm($data['term'] ?? null)
                ->setContent($data['content'] ?? null)
                ->setFirstCalledAt($nowImmutable)
                ->setNbCalls(0)
                ->setBot($isBot)
            ;
            $em->persist($pageCall);
        }

        $pageCall->setNbCalls($pageCall->getNbCalls() + 1);
        $pageCall->setLastCalledAt($now);

        $ip = $request->getClientIp();
        $anonymizedIp = $ip ? preg_replace('/\.\d+$/', '.0', $ip) : null;
        $screen = $data['screen'] ?? [];

        $hit = new PageCallHit();
        $hit
            ->setPageCall($pageCall)
            ->setReferrer($request->headers->get('referer'))
            ->setUserAgent($userAgent)
            ->setAnonymizedIp($anonymizedIp)
            ->setLanguage($data['language'] ?? null)
            ->setCalledAt($nowImmutable)
            ->setPageTitle($data['title'] ?? null)
            ->setPageType($data['type'] ?? null)
            ->setScreenWidth($screen['width'] ?? null)
            ->setScreenHeight($screen['height'] ?? null)
            ->setBot($isBot);

        if (!empty($data['parentHitId'])) {
            $parentHit = $pageCallHitRepository->find($data['parentHitId']);
            if ($parentHit) {
                $hit->setParentHit($parentHit);
            }
        }

        $hit->updateDuration();
        $em->persist($hit);

        try {
            $em->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Database error', 'details' => $e->getMessage()], 500);
        }

        $dispatcher->dispatch(new PageCallTrackedEvent($pageCall, $hit));

        return new JsonResponse(['hitId' => $hit->getId()]);
    }

    #[Route('/page-call/exit', name: 'page_call_exit', methods: ['POST'])]
    public function exit(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $hitId = $data['hitId'] ?? null;

        if (!$hitId) {
            return new JsonResponse(['error' => 'Missing hitId'], 400);
        }

        $repository = $em->getRepository(PageCallHit::class);
        $hit = $repository->find($hitId);

        if (!$hit || null !== $hit->getExitedAt()) {
            return new JsonResponse(['error' => 'Invalid or already completed hit'], 400);
        }

        $exitTime = new \DateTimeImmutable();
        $hit->setExitedAt($exitTime);
        $hit->updateDuration();

        $em->flush();

        return new JsonResponse(['status' => 'ok']);
    }
}
