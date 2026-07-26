<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Retention;

final readonly class RetentionPurgeOptions
{
    public function __construct(
        public \DateTimeImmutable $cutoff,
        public bool $apply = false,
        public int $batchSize = 500,
        public bool $removeEmptyPageCalls = false,
    ) {
        if ($this->batchSize < 1) {
            throw new \InvalidArgumentException('The retention purge batch size must be at least 1.');
        }
    }
}
