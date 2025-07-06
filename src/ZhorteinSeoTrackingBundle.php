<?php

namespace Zhortein\SeoTrackingBundle;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Zhortein\SeoTrackingBundle\DependencyInjection\Compiler\DoctrineMappingPass;
use Zhortein\SeoTrackingBundle\DependencyInjection\Compiler\ResolveTargetEntitiesPass;
use Zhortein\SeoTrackingBundle\DependencyInjection\Extension\ZhorteinSeoTrackingExtension;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

class ZhorteinSeoTrackingBundle extends AbstractBundle
{
    public function prepend(ContainerBuilder $container): void
    {
        // Valeurs par défaut (en attendant que l’Extension injecte les vraies)
        $config = $container->getExtensionConfig('zhortein_seo_tracker')[0] ?? [];

        $pageCallClass = $config['page_call_class'] ?? PageCall::class;
        $pageCallHitClass = $config['page_call_hit_class'] ?? PageCallHit::class;

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'resolve_target_entities' => [
                    PageCallInterface::class => $pageCallClass,
                    PageCallHitInterface::class => $pageCallHitClass,
                ],
            ],
        ]);
    }

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
        $container->addCompilerPass(new ResolveTargetEntitiesPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new ZhorteinSeoTrackingExtension();
        }

        return false !== $this->extension ? $this->extension : null;
    }
}
