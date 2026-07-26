<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics;

use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

interface StatisticsProviderInterface
{
    public function report(?StatisticsFilter $filter = null, int $limit = 10): StatisticsReport;
}
