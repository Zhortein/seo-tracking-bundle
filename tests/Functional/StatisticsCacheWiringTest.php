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
use Zhortein\SeoTrackingBundle\Statistics\Cache\NullStatisticsReportCache;
use Zhortein\SeoTrackingBundle\Statistics\Cache\Psr6StatisticsReportCache;
use Zhortein\SeoTrackingBundle\Statistics\Cache\StatisticsReportCacheInterface;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\CachedStatisticsTestKernel;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\InMemoryCacheItemPool;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

final class StatisticsCacheWiringTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testCacheIsDisabledByDefault(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            self::assertInstanceOf(
                NullStatisticsReportCache::class,
                $kernel->getContainer()->get('test.statistics_report_cache'),
            );
        } finally {
            $kernel->shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testConfiguredPsr6PoolCachesReportsWithTheConfiguredTtl(): void
    {
        $kernel = new CachedStatisticsTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $provider = $container->get(StatisticsProviderInterface::class);
            $cache = $container->get(StatisticsReportCacheInterface::class);
            $pool = $container->get('test.statistics_cache_pool');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(StatisticsProviderInterface::class, $provider);
            self::assertInstanceOf(Psr6StatisticsReportCache::class, $cache);
            self::assertInstanceOf(InMemoryCacheItemPool::class, $pool);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            self::assertSame($provider->report(), $provider->report());
            self::assertSame(1, $pool->count());
            self::assertSame(60, $pool->lastTtl);
        } finally {
            $kernel->shutdown();
        }
    }
}
