<?php

namespace Zhortein\SeoTrackingBundle\Controller;

use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Repository\PageCallRepository;
use Zhortein\SeoTrackingBundle\Repository\PageCallHitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PageCallController extends AbstractController
{
    #[Route('/page-call/track', name: 'page_call_track', methods: ['POST'])]
    public function track(Request $request, PageCallRepository $pageCallRepository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $nowImmutable = new \DateTimeImmutable();
        $now = new \DateTime();

        $pageCall = $pageCallRepository->findOneBy([
            'url' => $data['url'],
            'campaign' => $data['campaign'],
            'medium' => $data['medium'],
            'source' => $data['source'],
            'term' => $data['term'],
            'content' => $data['content'],
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
                ->setNbCalls(0);
            $em->persist($pageCall);
        }

        $pageCall->setNbCalls($pageCall->getNbCalls() + 1);
        $pageCall->setLastCalledAt($now);

        $ip = $request->getClientIp();
        $anonymizedIp = $ip ? preg_replace('/\.\d+$/', '.0', $ip) : null;

        $hit = new PageCallHit();
        $hit
            ->setPageCall($pageCall)
            ->setReferrer($request->headers->get('referer'))
            ->setUserAgent($request->headers->get('User-Agent'))
            ->setAnonymizedIp($anonymizedIp)
            ->setCalledAt($nowImmutable);

        $em->persist($hit);
        $em->flush();

        return new JsonResponse(['hitId' => $hit->getId()]);
    }

    #[Route('/page-call/exit', name: 'page_call_exit', methods: ['POST'])]
    public function exit(Request $request, PageCallHitRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $hitId = $data['hitId'] ?? null;

        if (!$hitId) {
            return new JsonResponse(['error' => 'Missing hitId'], 400);
        }

        $hit = $repository->find($hitId);

        if (!$hit || $hit->getExitedAt() !== null) {
            return new JsonResponse(['error' => 'Invalid or already completed hit'], 400);
        }

        $exitTime = new \DateTimeImmutable();
        $hit->setExitedAt($exitTime);
        $hit->updateDuration();

        $em->flush();

        return new JsonResponse(['status' => 'ok']);
    }
}
