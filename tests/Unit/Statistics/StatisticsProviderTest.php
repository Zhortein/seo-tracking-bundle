<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Statistics;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProvider;

final class StatisticsProviderTest extends TestCase
{
    public function testItCalculatesOnlyReliableHitMetrics(): void
    {
        $observations = [
            new HitObservation(
                new \DateTimeImmutable('2026-07-01 22:30:00 UTC'),
                false,
                true,
                10,
                '/a',
                'article',
                'google',
                'summer',
                'cpc',
                'article',
                'en',
            ),
            new HitObservation(
                new \DateTimeImmutable('2026-07-01 23:30:00 UTC'),
                true,
                true,
                30,
                '/a',
                'article',
                'google',
                'summer',
                'cpc',
                'article',
                'en',
            ),
            new HitObservation(
                new \DateTimeImmutable('2026-07-02 23:30:00 UTC'),
                false,
                false,
                null,
                '/b',
                'home',
                'newsletter',
                null,
                'email',
                'home',
                'fr',
            ),
            new HitObservation(
                null,
                false,
                true,
                20,
                '/b',
                'home',
                null,
                null,
                null,
                'home',
                'fr',
            ),
        ];
        $dataSource = new class($observations) implements StatisticsDataSourceInterface {
            /**
             * @param list<HitObservation> $observations
             */
            public function __construct(private readonly array $observations)
            {
            }

            public function observations(StatisticsFilter $filter): iterable
            {
                yield from $this->observations;
            }
        };
        $filter = new StatisticsFilter(timezone: new \DateTimeZone('Europe/Paris'));

        $report = (new StatisticsProvider($dataSource))->report($filter);

        self::assertSame(4, $report->summary->pageCalls);
        self::assertSame(3, $report->summary->humanHits);
        self::assertSame(1, $report->summary->robotHits);
        self::assertSame(3, $report->summary->closedHits);
        self::assertSame(3, $report->summary->durationSamples);
        self::assertSame(20.0, $report->summary->averageDurationSeconds);
        self::assertSame(20.0, $report->summary->medianDurationSeconds);
        self::assertSame(['/a', '/b'], array_column($report->topPages, 'label'));
        self::assertSame([2, 2], array_column($report->topPages, 'count'));
        self::assertSame(['google', 'newsletter'], array_column($report->sources, 'label'));
        self::assertCount(2, $report->trend);
        self::assertSame('2026-07-02', $report->trend[0]->date->format('Y-m-d'));
        self::assertSame(2, $report->trend[0]->hits);
        self::assertSame('2026-07-03', $report->trend[1]->date->format('Y-m-d'));
    }

    public function testFilterAndLimitValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StatisticsFilter(
            new \DateTimeImmutable('2026-07-02'),
            new \DateTimeImmutable('2026-07-01'),
        );
    }
}
