<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Pagination;

use Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final readonly class ObservationBrowser implements ObservationBrowserInterface
{
    public function __construct(private StatisticsDataSourceInterface $dataSource)
    {
    }

    public function page(
        ?StatisticsFilter $filter = null,
        ?ObservationPageRequest $request = null,
    ): ObservationPage {
        $filter ??= new StatisticsFilter();
        $request ??= new ObservationPageRequest();
        $items = [];
        $position = 0;
        $hasMore = false;

        foreach ($this->dataSource->observations($filter) as $observation) {
            if ($position++ < $request->offset) {
                continue;
            }

            if (count($items) === $request->limit) {
                $hasMore = true;
                break;
            }

            $items[] = $observation;
        }

        return new ObservationPage($items, $request->offset, $request->limit, $hasMore);
    }
}
