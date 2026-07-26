<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zhortein\SeoTrackingBundle\Tracking\Consent\TrackingConsentCheckerInterface;

final class ConsentDeniedTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(DenyTrackingConsentChecker::class);
        $container->setAlias(TrackingConsentCheckerInterface::class, DenyTrackingConsentChecker::class);
    }
}
