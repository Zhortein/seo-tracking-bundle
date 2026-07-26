<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Retention;

final readonly class RetentionPolicy
{
    public function __construct(
        public ?int $days = null,
        public int $batchSize = 500,
        public bool $removeEmptyPageCalls = false,
    ) {
        if (null !== $this->days && $this->days < 1) {
            throw new \InvalidArgumentException('Retention days must be null or at least 1.');
        }

        if ($this->batchSize < 1) {
            throw new \InvalidArgumentException('The retention batch size must be at least 1.');
        }
    }
}
