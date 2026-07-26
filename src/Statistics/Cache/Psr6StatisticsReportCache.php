<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;

final readonly class Psr6StatisticsReportCache implements StatisticsReportCacheInterface
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    public function remember(string $key, int $ttl, \Closure $compute): StatisticsReport
    {
        if ($ttl < 1) {
            throw new \InvalidArgumentException('The statistics cache TTL must be positive.');
        }

        try {
            $item = $this->pool->getItem($key);
            $cached = $item->get();
            if ($item->isHit() && $cached instanceof StatisticsReport) {
                return $cached;
            }
        } catch (\Throwable) {
            return $compute();
        }

        $report = $compute();

        try {
            $item->set($report);
            $item->expiresAfter($ttl);
            $this->pool->save($item);
        } catch (\Throwable) {
            // Reporting remains available if an optional cache backend fails.
        }

        return $report;
    }
}
