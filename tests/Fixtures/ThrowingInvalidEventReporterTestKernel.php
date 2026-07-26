<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReporterInterface;

final class ThrowingInvalidEventReporterTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(ThrowingInvalidTrackingEventReporter::class);
        $container->setAlias(
            InvalidTrackingEventReporterInterface::class,
            ThrowingInvalidTrackingEventReporter::class,
        );
    }
}
