<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DTO;

final readonly class TrendPoint
{
    public function __construct(
        public \DateTimeImmutable $date,
        public int $hits,
        public int $humanHits,
        public int $robotHits,
    ) {
    }
}
