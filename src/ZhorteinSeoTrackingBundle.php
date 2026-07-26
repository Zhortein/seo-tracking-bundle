<?php

namespace Zhortein\SeoTrackingBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Zhortein\SeoTrackingBundle\DependencyInjection\Compiler\DoctrineMappingPass;
use Zhortein\SeoTrackingBundle\DependencyInjection\Extension\ZhorteinSeoTrackingExtension;

class ZhorteinSeoTrackingBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DoctrineMappingPass());
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new ZhorteinSeoTrackingExtension();
        }

        return false !== $this->extension ? $this->extension : null;
    }
}
