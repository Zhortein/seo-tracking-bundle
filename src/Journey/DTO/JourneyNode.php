<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DTO;

final readonly class JourneyNode
{
    public string $label;

    public function __construct(
        public string $pageUrl,
        public ?string $route,
        public ?string $pageType,
    ) {
        $route = null === $route ? '' : trim($route);
        $this->label = '' === $route ? $pageUrl : $route;
    }

    public function key(): string
    {
        return hash('sha256', implode("\0", [
            $this->pageUrl,
            $this->route ?? '',
            $this->pageType ?? '',
        ]));
    }
}
