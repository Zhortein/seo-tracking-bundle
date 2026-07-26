# Upgrade to 1.7

Version 1.7 adds bounded statistics pagination, disabled-by-default report caching, lazy CSV exports and an optional semantic HTML5 theme. It does not change collection payloads, persisted entities or Doctrine mappings.

## Compatibility

The supported runtime remains PHP 8.3 or later, Symfony and AssetMapper 7.3, 7.4 or 8.x, Doctrine DBAL 3 or 4, Doctrine ORM 3.3 or later or 4.x, and Stimulus 3.

Composer installs the PSR cache interfaces required by the optional cache adapter. It does not select or enable an application cache backend.

## Update

1. Back up the application according to the normal deployment procedure.
2. Update the bundle and review the lockfile:

   ```bash
   composer require zhortein/seo-tracking-bundle:^1.7
   ```

3. Clear the Symfony cache through the application's normal process.
4. Recompile the asset map:

   ```bash
   php bin/console asset-map:compile
   ```

5. Exercise the existing collection endpoint and statistics report before enabling any optional 1.7 feature.

No Doctrine migration, index, constraint or data backfill is required. Applications upgrading from a release before 1.6 must still complete the applicable 1.3 and 1.6 schema procedures.

## Adopt bounded observation pages

Inject `ObservationBrowserInterface` only in application controllers that need row-level listings. `ObservationPageRequest` defaults to 50 rows, accepts at most 500 and rejects negative offsets.

The default browser applies the complete `StatisticsFilter` before pagination and reads one extra observation to expose `hasMore`. Restrict the controller using application authorization: URLs and business dimensions can still expose confidential context.

Offset pagination bounds response memory but does not make deep offsets constant-time or provide snapshot consistency during concurrent inserts. Replace the browser with a database-specific cursor implementation if those properties are required.

## Enable report caching deliberately

Caching remains disabled unless both a PSR-6 pool and a positive TTL are configured:

```yaml
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

Choose a TTL that matches the dashboard freshness requirement. The bundle does not invalidate an entire pool after every hit. Backend read or write failures are absorbed and the report is recomputed.

Configure neither value to keep the historical uncached behavior. Supplying only a pool or only a TTL is rejected during container configuration.

## Expose CSV only through an authorized application route

`CsvStatisticsExporterInterface` returns a lazy iterable and registers no route. The application must provide:

- access control at least as strict as the statistics dashboard;
- a bounded date/filter scope appropriate to the installation;
- `Content-Type` and safe attachment headers;
- download auditing, rate limiting and retention where required.

Do not materialize the iterable with `iterator_to_array()` for a large export. Formula-like string cells are protected, but the resulting file can still contain confidential URLs or controlled business dimensions.

## Choose or override presentation

Bootstrap 5 remains the default, so existing configuration requires no change. Select the dependency-free semantic theme explicitly:

```yaml
zhortein_seo_tracking:
    statistics:
        theme: html5
```

Set `theme: none` to expose only the typed provider and Twig function, or set `statistics.template` to an application template. An explicit template continues to take precedence over a theme.

Bundled English and French labels use the `seo_tracking` translation domain and can be overridden normally in the host application. Both templates preserve the `summary`, `trend`, `rankings` and `dimensions` blocks.

## Verification

In staging, verify:

1. the Doctrine schema diff is empty after updating from 1.6;
2. an existing report has the same totals and rankings before and after the update;
3. a page of filtered observations respects its limit and returns the expected next offset;
4. cache-disabled reports remain fresh and a configured short-TTL pool reuses only equivalent filters;
5. CSV exports use the documented header, apply the complete filter and open without formula evaluation;
6. the selected locale and theme render exact counts and the complete daily table;
7. authorization prevents untrusted access to row-level listings and exports.

## Rollback

Before downgrading application code to 1.6:

- remove `statistics.cache` pool and TTL configuration;
- stop injecting the new pagination and CSV export interfaces;
- remove application routes or jobs that expose 1.7 exports;
- switch `statistics.theme: html5` back to `bootstrap5`, `none` or an application template;
- deploy the previous dependency version, clear Symfony's cache and recompile the asset map.

No database rollback is required. Existing PSR-6 entries can expire normally or be cleared from their dedicated application pool. Previously generated CSV files are external artifacts and must follow the application's own retention and revocation procedures.
