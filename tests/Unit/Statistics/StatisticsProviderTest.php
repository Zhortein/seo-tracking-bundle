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
                ['tenant' => 'acme', 'tier' => 1, 'enabled' => true],
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
                ['tenant' => 'acme', 'tier' => '1', 'enabled' => false],
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
                ['tenant' => 'beta', 'tier' => 1],
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
        self::assertSame(['enabled', 'tenant', 'tier'], array_column($report->dimensions, 'name'));
        self::assertSame(['acme', 'beta'], array_column($report->dimensions[1]->values, 'value'));
        self::assertSame([2, 1], array_column($report->dimensions[1]->values, 'count'));
        self::assertSame([1, '1'], array_column($report->dimensions[2]->values, 'value'));
        self::assertSame(['integer', 'string'], array_column($report->dimensions[2]->values, 'type'));
        self::assertSame(['false', 'true'], array_column($report->dimensions[0]->values, 'label'));
        self::assertSame(['boolean', 'boolean'], array_column($report->dimensions[0]->values, 'type'));
    }

    public function testFilterAndLimitValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StatisticsFilter(
            new \DateTimeImmutable('2026-07-02'),
            new \DateTimeImmutable('2026-07-01'),
        );
    }

    public function testDimensionFiltersUseCollectionNormalization(): void
    {
        $filter = new StatisticsFilter(dimensions: [
            'tenant' => 'acme',
            'enabled' => true,
        ]);

        self::assertSame([
            'enabled' => true,
            'tenant' => 'acme',
        ], $filter->dimensions);

        $this->expectException(\InvalidArgumentException::class);
        new StatisticsFilter(dimensions: ['invalid key' => 'not-supported']);
    }
}
