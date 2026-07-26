<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DTO;

use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final readonly class StatisticsReport
{
    /**
     * @param list<RankedValue> $topPages
     * @param list<RankedValue> $sources
     * @param list<RankedValue> $campaigns
     * @param list<RankedValue> $mediums
     * @param list<RankedValue> $pageTypes
     * @param list<RankedValue> $routes
     * @param list<RankedValue> $languages
     * @param list<TrendPoint>  $trend
     */
    public function __construct(
        public StatisticsFilter $filter,
        public StatisticsSummary $summary,
        public array $topPages,
        public array $sources,
        public array $campaigns,
        public array $mediums,
        public array $pageTypes,
        public array $routes,
        public array $languages,
        public array $trend,
    ) {
    }
}
