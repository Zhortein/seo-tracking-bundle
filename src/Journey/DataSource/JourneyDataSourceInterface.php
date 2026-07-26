<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DataSource;

use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyHitObservation;
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;

interface JourneyDataSourceInterface
{
    /**
     * Returns hits selected by the filter and, when available, their immediate
     * persisted parent even if that parent falls outside the filter.
     *
     * @return iterable<JourneyHitObservation>
     */
    public function observations(JourneyFilter $filter): iterable;
}
