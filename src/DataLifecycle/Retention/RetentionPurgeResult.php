<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Retention;

final readonly class RetentionPurgeResult
{
    public function __construct(
        public \DateTimeImmutable $cutoff,
        public int $candidateHits,
        public int $purgedHits,
        public int $affectedPageCalls,
        public int $removedPageCalls,
        public int $undatedHits,
        public bool $applied,
    ) {
    }
}
