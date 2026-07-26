<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DTO;

final readonly class JourneyPath
{
    /**
     * @param non-empty-list<JourneyStep> $steps
     */
    public function __construct(
        public array $steps,
        public bool $hasKnownPredecessorOutsideFilter,
        public bool $cyclic,
        public bool $truncatedByDepth,
    ) {
    }
}
