<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Zhortein\SeoTrackingBundle\ZhorteinSeoTrackingBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new TwigBundle();
        yield new ZhorteinSeoTrackingBundle();
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/zhortein-seo-tracking-bundle-tests';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
        ]);
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            'orm' => [
                'auto_mapping' => true,
            ],
        ]);
        $container->loadFromExtension('twig', [
            'strict_variables' => true,
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(dirname(__DIR__, 2).'/config/routes.yaml');
    }
}
