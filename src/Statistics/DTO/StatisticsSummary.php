<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DTO;

final readonly class StatisticsSummary
{
    public function __construct(
        public int $pageCalls,
        public int $humanHits,
        public int $robotHits,
        public int $closedHits,
        public int $durationSamples,
        public ?float $averageDurationSeconds,
        public ?float $medianDurationSeconds,
    ) {
    }
}
