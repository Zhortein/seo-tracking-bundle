<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Statistics\Export\CsvStatisticsExporterInterface;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationBrowserInterface;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationPageRequest;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

final class StatisticsProviderTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDoctrineSourceAppliesPeriodRobotAndPageTypeFilters(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $provider = $container->get(StatisticsProviderInterface::class);
            $browser = $container->get(ObservationBrowserInterface::class);
            $exporter = $container->get(CsvStatisticsExporterInterface::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(StatisticsProviderInterface::class, $provider);
            self::assertInstanceOf(ObservationBrowserInterface::class, $browser);
            self::assertInstanceOf(CsvStatisticsExporterInterface::class, $exporter);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            $this->persistHit(
                $entityManager,
                'human-article',
                new \DateTimeImmutable('2026-07-10 10:00:00 UTC'),
                false,
                'article',
                12,
                ['tenant' => 'acme', 'plan' => 'pro', 'version' => 1],
            );
            $this->persistHit(
                $entityManager,
                'human-article-other-tenant',
                new \DateTimeImmutable('2026-07-10 11:00:00 UTC'),
                false,
                'article',
                18,
                ['tenant' => 'acme', 'plan' => 'free', 'version' => '1'],
            );
            $this->persistHit(
                $entityManager,
                'robot-home',
                new \DateTimeImmutable('2026-07-11 10:00:00 UTC'),
                true,
                'home',
                30,
                [],
            );
            $entityManager->flush();

            $report = $provider->report(new StatisticsFilter(
                from: new \DateTimeImmutable('2026-07-10 00:00:00 UTC'),
                to: new \DateTimeImmutable('2026-07-10 23:59:59 UTC'),
                bot: false,
                pageType: 'article',
                dimensions: ['tenant' => 'acme', 'version' => 1],
            ));

            self::assertSame(1, $report->summary->pageCalls);
            self::assertSame(1, $report->summary->humanHits);
            self::assertSame(0, $report->summary->robotHits);
            self::assertSame(12.0, $report->summary->averageDurationSeconds);
            self::assertSame('https://example.test/human-article', $report->topPages[0]->label);
            self::assertSame(['plan', 'tenant', 'version'], array_column($report->dimensions, 'name'));
            self::assertSame('pro', $report->dimensions[0]->values[0]->value);
            self::assertSame('integer', $report->dimensions[2]->values[0]->type);

            $page = $browser->page(
                new StatisticsFilter(
                    from: new \DateTimeImmutable('2026-07-10 00:00:00 UTC'),
                    to: new \DateTimeImmutable('2026-07-10 23:59:59 UTC'),
                    bot: false,
                    pageType: 'article',
                    dimensions: ['tenant' => 'acme'],
                ),
                new ObservationPageRequest(0, 1),
            );

            self::assertSame(['https://example.test/human-article'], array_column($page->items, 'pageUrl'));
            self::assertTrue($page->hasMore);
            self::assertSame(1, $page->nextOffset());

            $csv = implode('', iterator_to_array($exporter->export(new StatisticsFilter(
                from: new \DateTimeImmutable('2026-07-10 00:00:00 UTC'),
                to: new \DateTimeImmutable('2026-07-10 23:59:59 UTC'),
                bot: false,
                pageType: 'article',
                dimensions: ['tenant' => 'acme', 'version' => 1],
            )), false));

            self::assertStringContainsString('"https://example.test/human-article"', $csv);
            self::assertStringContainsString('"{""plan"":""pro"",""tenant"":""acme"",""version"":1}"', $csv);
            self::assertStringNotContainsString('human-article-other-tenant', $csv);
            self::assertStringNotContainsString('robot-home', $csv);
            self::assertSame(2, substr_count($csv, "\n"));
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @param array<string, string|int|float|bool> $dimensions
     */
    private function persistHit(
        EntityManagerInterface $entityManager,
        string $slug,
        \DateTimeImmutable $calledAt,
        bool $bot,
        string $pageType,
        int $duration,
        array $dimensions,
    ): void {
        $pageCall = (new PageCall())
            ->setUrl('https://example.test/'.$slug)
            ->setGroupingKey(hash('sha256', $slug))
            ->setNbCalls(1)
            ->setFirstCalledAt($calledAt)
            ->setLastCalledAt(\DateTime::createFromImmutable($calledAt))
            ->setBot($bot);
        $hit = (new PageCallHit())
            ->setPageCall($pageCall)
            ->setCalledAt($calledAt)
            ->setExitedAt($calledAt->modify(sprintf('+%d seconds', $duration)))
            ->setPageType($pageType)
            ->setLanguage('en')
            ->setDimensions($dimensions)
            ->setBot($bot);

        $entityManager->persist($pageCall);
        $entityManager->persist($hit);
    }
}
