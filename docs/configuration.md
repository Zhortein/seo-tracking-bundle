# Configuration reference

The configuration root is `zhortein_seo_tracking`. Every option is optional;
the defaults preserve immediate local collection, uncached statistics,
Bootstrap 5 presentation and no automatic retention.

Inspect the effective configuration in an application with:

```bash
php bin/console config:dump-reference zhortein_seo_tracking
php bin/console debug:config zhortein_seo_tracking
```

## Complete example with defaults

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    page_call_class: Zhortein\SeoTrackingBundle\Entity\PageCall
    page_call_hit_class: Zhortein\SeoTrackingBundle\Entity\PageCallHit

    anonymization:
        ipv4_prefix: 24
        ipv6_prefix: 64

    tracking_url: null
    exit_url: null

    statistics:
        theme: bootstrap5
        template: null
        cache:
            pool: null
            ttl: 0

    retention:
        days: null
        batch_size: 500
        remove_empty_page_calls: false

    consent:
        grant_event: 'seo-tracking:consent-granted'
        revoke_event: 'seo-tracking:consent-revoked'

    rate_limiter:
        creation_limiter: null
        closure_limiter: null

    easylyse_enabled: false
    easylyse_api_key: ''
    easylyse_api_page_call_endpoint: 'https://www.easylyse.fr/fr/api/seo/hit'
    easylyse_api_page_exit_endpoint: 'https://www.easylyse.fr/fr/api/seo/exit'
    easylyse_timeout: 300
    auto_send: false
```

Do not copy this complete block merely to restate defaults. Configure only the
policies the application has deliberately chosen.

## Entity classes

| Option | Default | Contract |
|---|---|---|
| `page_call_class` | Bundled `PageCall` | Non-empty class implementing `PageCallInterface`. |
| `page_call_hit_class` | Bundled `PageCallHit` | Non-empty class implementing `PageCallHitInterface`. |

Doctrine target-entity resolution and the controller use these classes.
Constructor arguments can be handled by replacing the corresponding factories.
See [custom entities](custom-entities.md).

## IP anonymization

| Option | Default | Validation |
|---|---:|---|
| `anonymization.ipv4_prefix` | `24` | Integer from 0 to 32. |
| `anonymization.ipv6_prefix` | `64` | Integer from 0 to 128. |

The value stored on a hit is the masked network address. A prefix of 0 removes
all address bits; a full prefix retains the complete address and therefore
requires a deliberate privacy assessment.

Replace `IpAnonymizerInterface` for another policy.

## Endpoint URLs

| Option | Default | Behavior |
|---|---|---|
| `tracking_url` | `null` | Generates route `seo_tracking_page_call`. |
| `exit_url` | `null` | Generates route `seo_tracking_page_exit`. |

Set an explicit string only for a reverse proxy, another host or an
application-owned controller:

```yaml
zhortein_seo_tracking:
    tracking_url: '/analytics/page'
    exit_url: '/analytics/page/exit'
```

The Twig helper passes these values to Stimulus. Explicit cross-origin URLs
also require a compatible browser, CORS, credential and CSRF policy owned by
the application.

## Statistics

| Option | Default | Validation and behavior |
|---|---|---|
| `statistics.theme` | `bootstrap5` | One of `bootstrap5`, `html5` or `none`. |
| `statistics.template` | `null` | Non-empty Twig template name or `null`; takes precedence over the theme. |
| `statistics.cache.pool` | `null` | PSR-6 pool service ID or `null`. |
| `statistics.cache.ttl` | `0` | Non-negative seconds. Must be positive when a pool is set. |

Cache pool and TTL are a pair: configure both or neither. Backend failures are
fail-open and the report is recomputed. Read
[statistics pagination and cache](statistics-performance.md) before enabling
it.

`theme: none` keeps the provider and report Twig function but disables the
default renderer. Pass an explicit template when rendering or render the typed
DTOs yourself. See [statistics presentation](statistics-presentation.md).

## Retention

| Option | Default | Validation and behavior |
|---|---|---|
| `retention.days` | `null` | `null` disables the policy cutoff; otherwise integer ≥ 1. |
| `retention.batch_size` | `500` | Integer ≥ 1. |
| `retention.remove_empty_page_calls` | `false` | Whether the applied purge removes aggregates emptied by that purge. |

Configuration never schedules or applies a purge. The command remains a
dry-run until `--apply` is supplied. Read the
[retention and rollback guide](retention.md) before running it.

## Consent events

| Option | Default |
|---|---|
| `consent.grant_event` | `seo-tracking:consent-granted` |
| `consent.revoke_event` | `seo-tracking:consent-revoked` |

Both values must be non-empty and distinct. Event names only coordinate the
browser lifecycle; the replaceable server-side
`TrackingConsentCheckerInterface` remains authoritative. See
[consent integration](consent.md).

## Rate limiting

| Option | Default | Value |
|---|---|---|
| `rate_limiter.creation_limiter` | `null` | Complete `limiter.*` service ID for hit creation. |
| `rate_limiter.closure_limiter` | `null` | Complete `limiter.*` service ID for hit closure. |

Each endpoint is independent. `symfony/rate-limiter` is optional until one of
these options is configured. See [rate limiting](rate-limiting.md) for named
policy examples, response headers and trusted-proxy guidance.

## Easylyse forwarding

| Option | Default | Behavior |
|---|---|---|
| `easylyse_enabled` | `false` | Enables synchronous forwarding when the API key and endpoint are non-empty. |
| `easylyse_api_key` | empty string | Sent as `X-Api-Key`. |
| `easylyse_api_page_call_endpoint` | Easylyse page-call URL | Receives successful hit events. |
| `easylyse_api_page_exit_endpoint` | Easylyse exit URL | Receives successful closure events. |
| `easylyse_timeout` | `300` | HTTP client timeout in seconds. |
| `auto_send` | `false` | Accepted for historical compatibility but not read by 1.7; do not rely on it. |

Forwarding is disabled by default and is not required for local statistics.
It sends more data than the bundle's CSV exporter, including the stored
User-Agent, referrer and anonymized IP. Read the
[Easylyse forwarding guide](easylyse-forwarding.md) before enabling it.

## Replaceable services

The following public contracts are designed for application replacement or
decoration:

| Concern | Contract |
|---|---|
| Entity construction | `PageCallFactoryInterface`, `PageCallHitFactoryInterface` |
| Entity compatibility | `TrackingEntityAccessor` |
| IP policy | `IpAnonymizerInterface` |
| Bot behavior | `BotClassifierInterface`, legacy `BotDetectorInterface` |
| Consent | `TrackingConsentCheckerInterface` |
| Endpoint limiting | `TrackingRateLimiterInterface`, `TrackingRateLimitKeyResolverInterface` |
| Invalid requests | `InvalidTrackingEventReporterInterface` |
| Statistics input | `StatisticsDataSourceInterface` |
| Report cache | `StatisticsReportCacheInterface` |
| Observation pages | `ObservationBrowserInterface` |
| CSV export | `CsvStatisticsExporterInterface` |
| Journey input | `JourneyDataSourceInterface` |
| Retention | `HitRetentionPurgerInterface` |
| Historical duplicate merge | `DuplicatePageCallMergerInterface` |

Register a replacement using a normal Symfony alias:

```yaml
# config/services.yaml
services:
    App\Analytics\CustomStatisticsDataSource: ~

    Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface:
        alias: App\Analytics\CustomStatisticsDataSource
```

The topic guides document each contract's semantic requirements. Replacements
must preserve filter, privacy and failure behavior expected by the public API.
