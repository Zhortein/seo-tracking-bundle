<?php

namespace Zhortein\SeoTrackingBundle\DependencyInjection\Extension;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Zhortein\SeoTrackingBundle\DependencyInjection\Configuration;

class ZhorteinSeoTrackingExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../config'));
        $loader->load('services.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->handleBundleRoutes($container);
    }

    protected function handleBundleRoutes(ContainerBuilder $container): void
    {
        $filesystem = new Filesystem();
        /** @var string|null $projectPath */
        $projectPath = $container->getParameter('kernel.project_dir');
        $filePath = $projectPath.'/config/routes/zhortein_seo_tracking.yaml';

        if (!$filesystem->exists($filePath)) {
            $filesystem->dumpFile($filePath, <<<YAML
zhortein_seo_tracking:
    resource: '@ZhorteinSeoTrackingBundle/config/routes.yaml'
YAML);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->configureAssetMapper($container);
    }

    private function configureAssetMapper(ContainerBuilder $container): void
    {
        if (!$this->isAssetMapperAvailable($container)) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/../../../assets/dist' => '@zhortein/seo-tracking-bundle',
                ],
            ],
        ]);
    }

    private function isAssetMapperAvailable(ContainerBuilder $container): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        /** @var array<string, string|int|bool|float|null> $frameworkBundle */
        $frameworkBundle = $container->getParameter('kernel.bundles_metadata')['FrameworkBundle'] ?? null;

        return $frameworkBundle && is_file($frameworkBundle['path'].'/Resources/config/asset_mapper.php');
    }
}
