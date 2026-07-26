<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Cache;

use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final readonly class StatisticsCacheKeyGenerator
{
    public function generate(StatisticsFilter $filter, int $limit): string
    {
        $payload = [
            'version' => 1,
            'from' => $this->date($filter->from),
            'to' => $this->date($filter->to),
            'timezone' => $filter->timezone->getName(),
            'bot' => $filter->bot,
            'pageType' => $filter->pageType,
            'dimensions' => $filter->dimensions,
            'limit' => $limit,
        ];

        return 'seo_tracking.statistics.report.v1.'.hash(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        );
    }

    private function date(?\DateTimeImmutable $date): ?string
    {
        return $date?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
