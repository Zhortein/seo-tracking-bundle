<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Grouping;

final readonly class GroupingBackfillResult
{
    public function __construct(
        public int $scanned,
        public int $backfillable,
        public int $conflicts,
        public int $merged,
        public int $invalid,
        public bool $applied,
    ) {
    }
}
