# Statistics pagination and cache

Version 1.7 adds two independent tools for applications that expose statistics over growing hit tables:

- a bounded observation browser for administrative listings;
- an opt-in PSR-6 cache for repeated aggregate reports.

Neither feature changes collection, retention or the meaning of a report. They introduce no visitor, device or session identity.

## Browse filtered observations

Inject `ObservationBrowserInterface` and reuse the same `StatisticsFilter` used by aggregate reports:

```php
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationBrowserInterface;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationPageRequest;

final readonly class AnalyticsController
{
    public function __construct(
        private ObservationBrowserInterface $observations,
    ) {
    }

    public function page(int $offset = 0): array
    {
        $page = $this->observations->page(
            new StatisticsFilter(
                from: new \DateTimeImmutable('-30 days'),
                bot: false,
                dimensions: ['tenant' => 'acme'],
            ),
            new ObservationPageRequest(offset: $offset, limit: 50),
        );

        return [
            'items' => $page->items,
            'hasMore' => $page->hasMore,
            'nextOffset' => $page->nextOffset(),
        ];
    }
}
```

The offset must be zero or greater. Page sizes are limited to 500 observations and default to 50. The browser reads only through the requested window plus one look-ahead observation, so it does not materialize every matching hit.

Pagination is applied after the configured data source has enforced the complete filter, including exact typed dimensions. This preserves custom data-source compatibility and portable JSON semantics.

Offset pagination bounds response size and memory; it does not make deep offsets constant-time. A deep page still walks preceding filtered observations, and concurrent inserts can move offsets. Applications with high-volume or snapshot-consistency requirements should replace `ObservationBrowserInterface` with a database-specific cursor implementation. Pre-aggregated storage remains a separate concern.

Restrict this listing using application authorization. Observations contain URLs and application-defined business dimensions and can expose sensitive operational context even though the bundle does not include raw IP addresses or stable identity.

## Enable aggregate report caching

Caching is disabled by default. To enable it, provide both a PSR-6 pool service and a positive TTL:

```yaml
# config/packages/zhortein_seo_tracking.yaml
framework:
    cache:
        pools:
            cache.seo_tracking_statistics:
                adapter: cache.app

zhortein_seo_tracking:
    statistics:
        cache:
            pool: cache.seo_tracking_statistics
            ttl: 60
```

Use a dedicated pool when the adapter supports it. The bundle stores `StatisticsReport` values using keys derived from:

- the inclusive start and end instants normalized to UTC;
- the requested display timezone;
- the robot and page-type filters;
- every type-sensitive custom dimension;
- the ranking limit;
- an internal cache-key schema version.

Equivalent instants and normalized dimension maps therefore share a key, while the integer `1`, float `1.0` and string `"1"` remain distinct.

The TTL is deliberately explicit because tracking creates and closes hits continuously. The bundle does not clear an entire application cache pool after each hit. A cache backend read or write failure is fail-open: the requested report is computed and returned instead of turning an optional optimization into an analytics outage.

Choose the TTL according to the dashboard:

- short TTLs for launch or operational monitoring;
- longer TTLs for historical reports whose end date is in the past;
- cache disabled for views that must reflect a just-created hit immediately.

The cache avoids repeated aggregation but does not accelerate the first computation of a key. For installations where one full scan is already too expensive, replace `StatisticsDataSourceInterface` with a database-specific or pre-aggregated implementation while preserving the report API.

## Replace either policy

Both contracts are application-replaceable:

```yaml
services:
    Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationBrowserInterface:
        alias: App\Analytics\CursorObservationBrowser

    Zhortein\SeoTrackingBundle\Statistics\Cache\StatisticsReportCacheInterface:
        alias: App\Analytics\TaggedStatisticsReportCache
```

Custom caches must return only a `StatisticsReport` for the supplied key and must not change filter semantics. Custom browsers must apply the supplied `StatisticsFilter` before constructing the requested page.
