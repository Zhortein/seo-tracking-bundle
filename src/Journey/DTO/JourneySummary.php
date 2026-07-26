<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DTO;

final readonly class JourneySummary
{
    public function __construct(
        public int $observedHits,
        public int $linkedHits,
        public int $fragments,
        public int $paths,
        public int $sampledPaths,
        public int $maxDepth,
        public int $orphanedLinks,
        public int $cyclicPaths,
        public int $truncatedPaths,
    ) {
    }
}
