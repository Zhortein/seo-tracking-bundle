<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Grouping;

final readonly class GroupingBackfillOptions
{
    public function __construct(
        public bool $apply = false,
        public bool $mergeDuplicates = false,
        public int $batchSize = 100,
    ) {
        if ($this->batchSize < 1) {
            throw new \InvalidArgumentException('The grouping-key backfill batch size must be at least 1.');
        }
    }
}
