<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Pagination;

use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;

final readonly class ObservationPage
{
    /**
     * @param list<HitObservation> $items
     */
    public function __construct(
        public array $items,
        public int $offset,
        public int $limit,
        public bool $hasMore,
    ) {
        if ($offset < 0 || $limit < 1 || $limit > ObservationPageRequest::MAX_LIMIT) {
            throw new \InvalidArgumentException('Invalid statistics observation page bounds.');
        }

        if (count($items) > $limit) {
            throw new \InvalidArgumentException('A statistics observation page cannot contain more items than its limit.');
        }
    }

    public function nextOffset(): ?int
    {
        return $this->hasMore ? $this->offset + count($this->items) : null;
    }
}
