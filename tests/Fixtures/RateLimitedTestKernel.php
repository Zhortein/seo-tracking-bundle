<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RateLimitedTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->loadFromExtension('framework', [
            'rate_limiter' => [
                'seo_tracking_creation' => [
                    'policy' => 'fixed_window',
                    'limit' => 1,
                    'interval' => '1 hour',
                ],
                'seo_tracking_closure' => [
                    'policy' => 'fixed_window',
                    'limit' => 1,
                    'interval' => '1 hour',
                ],
            ],
        ]);
        $container->loadFromExtension('zhortein_seo_tracking', [
            'rate_limiter' => [
                'creation_limiter' => 'limiter.seo_tracking_creation',
                'closure_limiter' => 'limiter.seo_tracking_closure',
            ],
        ]);
    }
}
