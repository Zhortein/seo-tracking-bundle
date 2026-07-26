<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntity\LegacyPageCall;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntity\LegacyPageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntityTestKernel;

final class LegacyEntityStatisticsProviderTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDefaultSourceKeepsWorkingWithoutAnOptionalDimensionsField(): void
    {
        $kernel = new LegacyEntityTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $statistics = $container->get('test.statistics_provider');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(StatisticsProviderInterface::class, $statistics);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(LegacyPageCall::class),
                $entityManager->getClassMetadata(LegacyPageCallHit::class),
            ]);

            $calledAt = new \DateTimeImmutable('2026-07-10 10:00:00 UTC');
            $pageCall = (new LegacyPageCall())
                ->setUrl('https://example.test/legacy')
                ->setGroupingKey(hash('sha256', 'legacy'))
                ->setRoute('legacy')
                ->setNbCalls(1)
                ->setFirstCalledAt($calledAt)
                ->setLastCalledAt(\DateTime::createFromImmutable($calledAt))
                ->setBot(false);
            $hit = (new LegacyPageCallHit())
                ->setPageCall($pageCall)
                ->setCalledAt($calledAt)
                ->setBot(false);
            $entityManager->persist($pageCall);
            $entityManager->persist($hit);
            $entityManager->flush();

            $report = $statistics->report();
            self::assertSame(1, $report->summary->pageCalls);
            self::assertSame([], $report->dimensions);

            $filtered = $statistics->report(new StatisticsFilter(dimensions: ['tenant' => 'acme']));
            self::assertSame(0, $filtered->summary->pageCalls);
        } finally {
            $kernel->shutdown();
        }
    }
}
