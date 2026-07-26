<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Export;

use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

interface CsvStatisticsExporterInterface
{
    /**
     * @return iterable<string>
     */
    public function export(
        ?StatisticsFilter $filter = null,
        ?CsvExportOptions $options = null,
    ): iterable;
}
