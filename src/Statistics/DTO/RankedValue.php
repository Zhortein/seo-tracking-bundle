<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DTO;

final readonly class RankedValue
{
    public function __construct(
        public string $label,
        public int $count,
    ) {
    }
}
