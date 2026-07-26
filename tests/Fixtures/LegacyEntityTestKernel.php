<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntity\LegacyPageCall;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntity\LegacyPageCallHit;

final class LegacyEntityTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->loadFromExtension('zhortein_seo_tracking', [
            'page_call_class' => LegacyPageCall::class,
            'page_call_hit_class' => LegacyPageCallHit::class,
        ]);
        $container->loadFromExtension('doctrine', [
            'orm' => [
                'mappings' => [
                    'SeoTrackingLegacyTestEntities' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => __DIR__.'/LegacyEntity',
                        'prefix' => 'Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntity',
                    ],
                ],
            ],
        ]);
    }
}
