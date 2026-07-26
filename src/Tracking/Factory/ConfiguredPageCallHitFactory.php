<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Factory;

use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;

final readonly class ConfiguredPageCallHitFactory implements PageCallHitFactoryInterface
{
    /**
     * @param class-string<PageCallHitInterface> $className
     */
    public function __construct(private string $className)
    {
    }

    public function create(): PageCallHitInterface
    {
        return new $this->className();
    }
}
