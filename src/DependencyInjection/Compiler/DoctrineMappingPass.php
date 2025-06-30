<?php

namespace Zhortein\SeoTrackingBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineMappingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(DoctrineOrmMappingsPass::class)) {
            return;
        }

        $mappings = [
            realpath(__DIR__ . '/../../Entity') => 'SeoTrackingBundle\Entity',
        ];

        $driver = DoctrineOrmMappingsPass::createAttributeMappingDriver($mappings, []);
        $container->addCompilerPass($driver);
    }
}