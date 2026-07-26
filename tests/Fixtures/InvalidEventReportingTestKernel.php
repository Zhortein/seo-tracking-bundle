<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReporterInterface;

class InvalidEventReportingTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(CollectingInvalidTrackingEventReporter::class)->setPublic(true);
        $container->setAlias(
            InvalidTrackingEventReporterInterface::class,
            CollectingInvalidTrackingEventReporter::class,
        );
    }
}
