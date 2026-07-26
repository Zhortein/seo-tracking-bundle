<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Factory;

use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

final readonly class ConfiguredPageCallFactory implements PageCallFactoryInterface
{
    /**
     * @param class-string<PageCallInterface> $className
     */
    public function __construct(private string $className)
    {
    }

    public function create(): PageCallInterface
    {
        return new $this->className();
    }
}
