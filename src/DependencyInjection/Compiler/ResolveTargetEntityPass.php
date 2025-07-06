<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

class ResolveTargetEntityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine.resolve_target_entity_listener')) {
            return;
        }

        $definition = $container->getDefinition('doctrine.resolve_target_entity_listener');

        $definition->addMethodCall('addResolveTargetEntity', [
            PageCallInterface::class,
            $container->getParameter('zhortein_seo_tracking.page_call_class'),
            [],
        ]);

        $definition->addMethodCall('addResolveTargetEntity', [
            PageCallHitInterface::class,
            $container->getParameter('zhortein_seo_tracking.page_call_hit_class'),
            [],
        ]);
    }
}
