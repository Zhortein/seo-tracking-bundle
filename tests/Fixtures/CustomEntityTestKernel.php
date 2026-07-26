<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCall;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCallHit;

final class CustomEntityTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->loadFromExtension('zhortein_seo_tracking', [
            'page_call_class' => CustomPageCall::class,
            'page_call_hit_class' => CustomPageCallHit::class,
        ]);
        $container->loadFromExtension('doctrine', [
            'orm' => [
                'mappings' => [
                    'SeoTrackingTestEntities' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity',
                    ],
                ],
            ],
        ]);
    }
}
