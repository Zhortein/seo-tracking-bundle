<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey;

use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyReport;
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;

interface JourneyProviderInterface
{
    public function report(
        ?JourneyFilter $filter = null,
        int $transitionLimit = 20,
        int $pathLimit = 20,
        int $maxDepth = 25,
    ): JourneyReport;
}
