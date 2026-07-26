<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Pagination;

use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

interface ObservationBrowserInterface
{
    public function page(
        ?StatisticsFilter $filter = null,
        ?ObservationPageRequest $request = null,
    ): ObservationPage;
}
