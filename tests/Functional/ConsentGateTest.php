<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zhortein\SeoTrackingBundle\Controller\PageCallController;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\ConsentDeniedTestKernel;

final class ConsentGateTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDeniedConsentBlocksNewTrackingButStillAllowsHitClosure(): void
    {
        $kernel = new ConsentDeniedTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $controller = $container->get(PageCallController::class);
            $dispatcher = $container->get(EventDispatcherInterface::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(PageCallController::class, $controller);
            self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            $denied = $controller->track(
                new Request(content: '{"url":"https://example.test/denied"}'),
                $entityManager,
                $dispatcher,
            );
            self::assertSame(403, $denied->getStatusCode());
            self::assertSame(0, $entityManager->getRepository(PageCallHit::class)->count([]));

            $pageCall = (new PageCall())
                ->setUrl('https://example.test/existing')
                ->setRoute(null)
                ->setRouteArgs(null)
                ->setCampaign(null)
                ->setMedium(null)
                ->setSource(null)
                ->setTerm(null)
                ->setContent(null)
                ->setNbCalls(1)
                ->setFirstCalledAt(new \DateTimeImmutable('2026-01-01'))
                ->setLastCalledAt(new \DateTime('2026-01-01'))
                ->setBot(false);
            $hit = (new PageCallHit())
                ->setPageCall($pageCall)
                ->setCalledAt(new \DateTimeImmutable('2026-01-01'))
                ->setBot(false);
            $entityManager->persist($pageCall);
            $entityManager->persist($hit);
            $entityManager->flush();
            self::assertNotNull($hit->getId());

            $closed = $controller->exit(
                new Request(content: json_encode(['hitId' => $hit->getId()], JSON_THROW_ON_ERROR)),
                $entityManager,
                $dispatcher,
            );
            self::assertSame(200, $closed->getStatusCode());
            self::assertNotNull($hit->getExitedAt());
        } finally {
            $kernel->shutdown();
        }
    }
}
