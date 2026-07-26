<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DTO;

final readonly class DimensionRanking
{
    /**
     * @param list<DimensionRankedValue> $values
     */
    public function __construct(
        public string $name,
        public array $values,
    ) {
    }
}
