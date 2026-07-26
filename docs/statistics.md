# Statistics API

Statistics are split into four layers:

1. `StatisticsDataSourceInterface` reads hit observations.
2. `StatisticsProviderInterface` calculates a report.
3. Typed DTOs expose the result.
4. Twig renders a selected template.

The default data source uses Doctrine and the configured hit entity class. The provider and DTOs do not depend on Twig or Bootstrap.

## Reliable metrics

The first API exposes only values supported by collected data:

- page calls, counted from hit rows;
- human and robot hits;
- number of closed hits;
- average and median duration, calculated only from closed hits with a non-negative duration;
- top pages;
- UTM sources, campaigns and media;
- route, page type and language breakdowns;
- typed rankings for every custom hit dimension;
- daily evolution in the requested timezone.

`durationSamples` states how many hits contribute to duration values. Open hits and legacy rows without a usable duration are excluded from average and median calculations.

The bundle does **not** expose unique visitors. An anonymized IP and a session-local parent-hit link are not a sound, stable visitor identity.

## PHP usage and filters

Inject `StatisticsProviderInterface`:

```php
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;

final readonly class AnalyticsController
{
    public function __construct(
        private StatisticsProviderInterface $statistics,
    ) {
    }

    public function report(): array
    {
        $filter = new StatisticsFilter(
            from: new \DateTimeImmutable('2026-07-01 00:00:00 UTC'),
            to: new \DateTimeImmutable('2026-07-31 23:59:59 UTC'),
            timezone: new \DateTimeZone('Europe/Paris'),
            bot: false,
            pageType: 'article',
            dimensions: [
                'tenant' => 'acme',
                'plan' => 'pro',
            ],
        );

        $report = $this->statistics->report($filter, limit: 10);

        return [
            'pageCalls' => $report->summary->pageCalls,
            'topPages' => $report->topPages,
            'dimensions' => $report->dimensions,
            'trend' => $report->trend,
        ];
    }
}
```

Filter values:

- `from` and `to` are inclusive instants;
- `timezone` controls daily grouping and display;
- `bot: null` includes all hits, `false` keeps humans, and `true` keeps robots;
- `pageType` matches the generic page type sent by the tracker.
- `dimensions` requires every supplied key/value pair to match exactly on a hit.

Dimension filters use the same keys, scalar types and bounds as collection. Matching is type-sensitive: the integer `1`, the float `1.0` and the string `"1"` are distinct. The default Doctrine source applies these matches after portable JSON hydration instead of relying on database-specific JSON operators.

The ranking limit must be between 1 and 100 and applies independently to the values of each dimension. `StatisticsReport::dimensions` contains a list of `DimensionRanking` DTOs; every ranked value exposes its scalar `value`, display `label`, `type` and `count`.

Dimension rankings can expose rare business values. They do not add authorization to a dashboard: restrict report access in the application, and never collect account, email, device or other user identifiers as dimensions.

## Twig usage

Render an all-time report with the selected theme:

```twig
{{ seo_tracking_statistics() }}
```

Build the report separately and pass it to the renderer:

```twig
{% set report = seo_tracking_statistics_report(filter, 20) %}
{{ seo_tracking_statistics(report) }}
```

Or select a template for one render:

```twig
{{ seo_tracking_statistics(report, 'analytics/report.html.twig') }}
```

Every template receives one variable named `report`, containing `StatisticsReport`.

## Theme selection and overrides

Bootstrap 5 is the default presentation theme:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    statistics:
        theme: bootstrap5
```

The theme only emits Bootstrap class names; it does not install Bootstrap or any JavaScript dependency.

Use an application template:

```yaml
zhortein_seo_tracking:
    statistics:
        template: 'analytics/statistics.html.twig'
```

Disable the supplied theme while keeping the provider and report Twig function:

```yaml
zhortein_seo_tracking:
    statistics:
        theme: none
```

With `theme: none`, pass a template to `seo_tracking_statistics()` or use `seo_tracking_statistics_report()` and render it yourself. Calling the renderer without any template raises a clear exception.

The bundled template can also be overridden at:

```text
templates/bundles/ZhorteinSeoTrackingBundle/statistics/bootstrap5/report.html.twig
```

It defines `summary`, `trend`, `rankings` and `dimensions` blocks for targeted overrides.

## Custom entities and data sources

Configured entities using `PageCallTrait` and `PageCallHitTrait` work without extra setup. For backward compatibility, a custom hit mapping without the optional `dimensions` field still produces reports with empty dimension rankings; a dimension filter matches none of those rows. A custom mapping that deliberately renames or omits other historical fields can replace the data source:

```yaml
services:
    App\Analytics\StatisticsDataSource: ~

    Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface:
        alias: App\Analytics\StatisticsDataSource
```

Return `HitObservation` objects after applying the supplied `StatisticsFilter`, including all exact dimension matches. Populate the observation's optional `dimensions` argument to expose dimension rankings. The standard provider, DTOs and Twig theme remain reusable.

The default implementation streams Doctrine scalar rows and aggregates them in PHP for database portability. For very large datasets, replace the data source with database-specific pre-aggregation while retaining the public report API.

## Pagination and cache

For administrative hit listings, `ObservationBrowserInterface` exposes bounded offset pages over the same filtered observation stream. It stops after the requested page plus one look-ahead item and returns explicit `hasMore` and `nextOffset()` information.

Aggregate report caching is disabled by default. Applications can configure a PSR-6 pool and positive TTL; cache keys cover every filter field, scalar dimension type, timezone and ranking limit. Optional cache failures are fail-open.

Read [statistics pagination and cache](statistics-performance.md) for configuration, performance boundaries, privacy considerations and replacement contracts.
