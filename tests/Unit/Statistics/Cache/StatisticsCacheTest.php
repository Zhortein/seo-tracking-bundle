<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Statistics\Cache;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Statistics\Cache\Psr6StatisticsReportCache;
use Zhortein\SeoTrackingBundle\Statistics\Cache\StatisticsCacheKeyGenerator;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsSummary;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\InMemoryCacheItemPool;

final class StatisticsCacheTest extends TestCase
{
    public function testCacheKeysAreStableAndTypeSensitive(): void
    {
        $generator = new StatisticsCacheKeyGenerator();
        $first = new StatisticsFilter(
            from: new \DateTimeImmutable('2026-07-01 12:00:00+02:00'),
            to: new \DateTimeImmutable('2026-07-02 12:00:00+02:00'),
            timezone: new \DateTimeZone('Europe/Paris'),
            bot: false,
            pageType: 'article',
            dimensions: ['version' => 1, 'enabled' => true],
        );
        $equivalent = new StatisticsFilter(
            from: new \DateTimeImmutable('2026-07-01 10:00:00 UTC'),
            to: new \DateTimeImmutable('2026-07-02 10:00:00 UTC'),
            timezone: new \DateTimeZone('Europe/Paris'),
            bot: false,
            pageType: 'article',
            dimensions: ['enabled' => true, 'version' => 1],
        );

        self::assertSame($generator->generate($first, 10), $generator->generate($equivalent, 10));
        self::assertNotSame(
            $generator->generate($first, 10),
            $generator->generate(new StatisticsFilter(dimensions: ['version' => '1']), 10),
        );
        self::assertNotSame($generator->generate($first, 10), $generator->generate($first, 20));
    }

    public function testPsr6CacheRemembersReportsWithTheConfiguredTtl(): void
    {
        $pool = new InMemoryCacheItemPool();
        $cache = new Psr6StatisticsReportCache($pool);
        $computations = 0;
        $compute = function () use (&$computations): StatisticsReport {
            ++$computations;

            return self::report();
        };

        $first = $cache->remember('statistics.key', 60, $compute(...));
        $second = $cache->remember('statistics.key', 60, $compute(...));

        self::assertSame($first, $second);
        self::assertSame(1, $computations);
        self::assertSame(60, $pool->lastTtl);
    }

    public function testOptionalCacheFailuresDoNotBreakReportComputation(): void
    {
        $pool = new InMemoryCacheItemPool();
        $pool->failReads = true;
        $cache = new Psr6StatisticsReportCache($pool);

        self::assertInstanceOf(
            StatisticsReport::class,
            $cache->remember('statistics.key', 60, self::report(...)),
        );

        $pool->failReads = false;
        $pool->failWrites = true;
        self::assertInstanceOf(
            StatisticsReport::class,
            $cache->remember('statistics.other', 60, self::report(...)),
        );
    }

    private static function report(): StatisticsReport
    {
        return new StatisticsReport(
            new StatisticsFilter(),
            new StatisticsSummary(0, 0, 0, 0, 0, null, null),
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
        );
    }
}
