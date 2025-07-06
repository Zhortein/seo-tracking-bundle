<?php

namespace Zhortein\SeoTrackingBundle\DependencyInjection\Compiler;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

class ResolveTargetEntitiesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('seo-tracking.page_call_hit_class')) {
            return;
        }

        $resolveTargets = [
            PageCallHitInterface::class => $container->getParameter('seo-tracking.page_call_hit_class'),
            PageCallInterface::class => $container->getParameter('seo-tracking.page_call_class'),
        ];

        $definition = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');

        foreach ($resolveTargets as $interface => $implementation) {
            $definition->addMethodCall('addResolveTargetEntity', [$interface, $implementation, []]);
        }
    }
}
