<?php

namespace Zhortein\SeoTrackingBundle;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Zhortein\SeoTrackingBundle\DependencyInjection\Compiler\DoctrineMappingPass;
use Zhortein\SeoTrackingBundle\DependencyInjection\Extension\ZhorteinSeoTrackingExtension;

class ZhorteinSeoTrackingBundle extends AbstractBundle
{
    /**
     * @param array<int|string, mixed> $config
     *
     * @see https://symfony.com/doc/current/bundles/configuration.html#using-the-abstractbundle-class
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        try {
            $loader = new XmlFileLoader($builder, new FileLocator(__DIR__.'/../config'));
            $loader->load('services.xml');
        } catch (\Exception) {
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DoctrineMappingPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new ZhorteinSeoTrackingExtension();
        }

        return false !== $this->extension ? $this->extension : null;
    }
}
