<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CachedStatisticsTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register('test.statistics_cache_pool', InMemoryCacheItemPool::class)->setPublic(true);
        $container->loadFromExtension('zhortein_seo_tracking', [
            'statistics' => [
                'cache' => [
                    'pool' => 'test.statistics_cache_pool',
                    'ttl' => 60,
                ],
            ],
        ]);
    }
}
