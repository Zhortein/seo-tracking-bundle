<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Cache;

use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;

interface StatisticsReportCacheInterface
{
    /**
     * @param \Closure(): StatisticsReport $compute
     */
    public function remember(string $key, int $ttl, \Closure $compute): StatisticsReport;
}
