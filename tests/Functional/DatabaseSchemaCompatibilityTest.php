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
use Zhortein\SeoTrackingBundle\DataLifecycle\Grouping\GroupingBackfillOptions;
use Zhortein\SeoTrackingBundle\DataLifecycle\Grouping\HistoricalGroupingKeyBackfiller;
use Zhortein\SeoTrackingBundle\DataLifecycle\Retention\HitRetentionPurger;
use Zhortein\SeoTrackingBundle\DataLifecycle\Retention\RetentionPurgeOptions;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Journey\JourneyProviderInterface;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

final class DatabaseSchemaCompatibilityTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDefaultSchemaAndNullUtmGroupingOnConfiguredDatabase(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $schemaTool = null;
        $metadata = [];

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $controller = $container->get(PageCallController::class);
            $dispatcher = $container->get(EventDispatcherInterface::class);
            $backfiller = $container->get(HistoricalGroupingKeyBackfiller::class);
            $purger = $container->get(HitRetentionPurger::class);
            $journeys = $container->get('test.journey_provider');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(PageCallController::class, $controller);
            self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
            self::assertInstanceOf(HistoricalGroupingKeyBackfiller::class, $backfiller);
            self::assertInstanceOf(HitRetentionPurger::class, $purger);
            self::assertInstanceOf(JourneyProviderInterface::class, $journeys);

            $metadata = [
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ];
            $schemaTool = new SchemaTool($entityManager);
            $schemaTool->createSchema($metadata);

            $request = new Request(content: '{"url":"https://example.test/database"}');
            $first = $controller->track($request, $entityManager, $dispatcher);
            $firstData = json_decode((string) $first->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($firstData);
            $second = $controller->track(
                new Request(content: json_encode([
                    'url' => 'https://example.test/database',
                    'parentHitId' => $firstData['hitId'],
                ], JSON_THROW_ON_ERROR)),
                $entityManager,
                $dispatcher,
            );

            self::assertSame(200, $first->getStatusCode(), (string) $first->getContent());
            self::assertSame(200, $second->getStatusCode(), (string) $second->getContent());
            self::assertSame(1, $entityManager->getRepository(PageCall::class)->count([]));
            self::assertSame(2, $entityManager->getRepository(PageCallHit::class)->count([]));
            $journeyReport = $journeys->report();
            self::assertSame(2, $journeyReport->summary->observedHits);
            self::assertSame(1, $journeyReport->summary->linkedHits);
            self::assertCount(1, $journeyReport->topTransitions);

            $historical = (new PageCall())
                ->setUrl('https://example.test/database')
                ->setRoute(null)
                ->setRouteArgs(null)
                ->setCampaign(null)
                ->setMedium(null)
                ->setSource(null)
                ->setTerm(null)
                ->setContent(null)
                ->setNbCalls(1)
                ->setFirstCalledAt(new \DateTimeImmutable('2025-01-01'))
                ->setLastCalledAt(new \DateTime('2025-01-01'))
                ->setBot(false);
            $historicalHit = (new PageCallHit())
                ->setPageCall($historical)
                ->setCalledAt(new \DateTimeImmutable('2025-01-01'))
                ->setBot(false);
            $entityManager->persist($historical);
            $entityManager->persist($historicalHit);
            $entityManager->flush();

            $result = $backfiller->run(new GroupingBackfillOptions(true, true, 1));
            self::assertSame(1, $result->merged);
            self::assertSame(1, $entityManager->getRepository(PageCall::class)->count([]));
            self::assertSame(3, $entityManager->getRepository(PageCallHit::class)->count([]));

            $purge = $purger->purge(new RetentionPurgeOptions(
                new \DateTimeImmutable('2026-01-01'),
                true,
                1,
            ));
            self::assertSame(1, $purge->purgedHits);
            self::assertSame(1, $entityManager->getRepository(PageCall::class)->count([]));
            self::assertSame(2, $entityManager->getRepository(PageCallHit::class)->count([]));

            $remainingPageCall = $entityManager->getRepository(PageCall::class)->findOneBy([]);
            self::assertInstanceOf(PageCall::class, $remainingPageCall);
            self::assertSame(2, $remainingPageCall->getNbCalls());
        } finally {
            if ($schemaTool instanceof SchemaTool && [] !== $metadata) {
                $schemaTool->dropSchema($metadata);
            }
            $kernel->shutdown();
        }
    }
}
