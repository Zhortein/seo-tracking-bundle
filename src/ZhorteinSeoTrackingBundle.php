<?php

namespace Zhortein\SeoTrackingBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Zhortein\SeoTrackingBundle\DependencyInjection\Extension\ZhorteinSeoTrackingExtension;

class ZhorteinSeoTrackingBundle extends AbstractBundle
{
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
