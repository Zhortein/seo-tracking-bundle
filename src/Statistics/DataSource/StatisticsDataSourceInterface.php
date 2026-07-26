<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DataSource;

use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

interface StatisticsDataSourceInterface
{
    /**
     * @return iterable<HitObservation>
     */
    public function observations(StatisticsFilter $filter): iterable;
}
