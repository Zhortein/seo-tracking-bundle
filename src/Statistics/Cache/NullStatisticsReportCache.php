<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Cache;

use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;

final readonly class NullStatisticsReportCache implements StatisticsReportCacheInterface
{
    public function remember(string $key, int $ttl, \Closure $compute): StatisticsReport
    {
        return $compute();
    }
}
