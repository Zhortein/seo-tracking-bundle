# SEO Tracking Bundle
Symfony bundle to track page views, UTM campaigns and basic engagement, with optional SEO insights integration.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zhortein/seo-tracking-bundle.svg)](https://packagist.org/packages/zhortein/seo-tracking-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/zhortein/seo-tracking-bundle.svg)](https://packagist.org/packages/zhortein/seo-tracking-bundle)
[![License](https://img.shields.io/packagist/l/zhortein/seo-tracking-bundle.svg)](https://github.com/Zhortein/seo-tracking-bundle/blob/main/LICENSE)

## 📦 Installation

```bash
composer require zhortein/seo-tracking-bundle
```

### Compatibility

| Layer | Supported versions |
|---|---|
| PHP | 8.3+ |
| Symfony and AssetMapper | 7.3, 7.4 and 8.x |
| Doctrine DBAL | 3.x and 4.x |
| DoctrineBundle | 2.15+ and 3.2+ |
| Doctrine ORM | 3.3+ and 4.x |
| Stimulus | 3.x |

CI exercises SQLite, PostgreSQL 16 and MySQL 8.4. The tracking and statistics abstractions can support other Doctrine platforms, but they are not part of the published CI guarantee.

If you're not using Symfony Flex, enable the bundle manually in config/bundles.php:

```php
Zhortein\SeoTrackingBundle\ZhorteinSeoTrackingBundle::class => ['all' => true],
```

## ⚠️ Database migration required!

After installing the bundle, and sometimes upgrading the bundle (check the [CHANGELOG](./CHANGELOG.md)), you must run a migration to create the required database tables:
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```
If you're using custom naming strategies or a prefixed schema, review the generated migration before applying it.

## ⚙️ Usage
To enable tracking, include the [Stimulus](https://stimulus.hotwired.dev/) controller in your layout or any page you want to track.

Add the following to your `<body>` tag to enable tracking:
```twig
<body {{ stimulus_controller('zhortein--seo-tracking-bundle--tracking', {
        route: app.request.attributes.get('_route'),
        routeArgs: app.request.attributes.get('_route_params'),
        type: 'home'
    }) }}>
```

We recommend placing the tracking call as early as possible in your page. The ```<body>``` tag is a good place for this.

The `type` value is optional and allows you to define the nature of the page (e.g. `home`, `contact`, `form`, etc.).  
This helps categorize traffic for SEO or UX analytics purposes.

### 🔧 Simplified usage with Twig helper

Instead of writing the `stimulus_controller(...)` call manually, you can use the built-in Twig function:

```twig
<div {{ seo_tracking('home') }}></div>
```

An explicit canonical URL can be supplied as the second argument:

```twig
<div {{ seo_tracking('article', canonical_url) }}></div>
```

Optional application-defined dimensions can be supplied as the third argument:

```twig
<div {{ seo_tracking('article', canonical_url, {
    content_category: article.category.slug,
    plan: current_plan_code
}) }}></div>
```

When it is omitted, the Stimulus controller uses the page's `<link rel="canonical">` when present, then falls back to the current URL for grouping.

This will generate:

```html
  data-controller="zhortein--seo-tracking-bundle--tracking"
  data-zhortein--seo-tracking-bundle--tracking-route-value="app_home"
  data-zhortein--seo-tracking-bundle--tracking-route-args-value="[]"
  data-zhortein--seo-tracking-bundle--tracking-type-value="home"
```

The function automatically injects the current Symfony route and route parameters.

> ✅ You can safely place this <div> in your layout or any tracked template.
> ⚠️ Do not use both `stimulus_controller(...)` and `seo_tracking(...)` at the same time.

## 🧠 What does this bundle track?

The bundle automatically collects basic visit tracking data using Stimulus and <code>fetch()</code> calls, without setting cookies.
Data is sent asynchronously when a page is loaded and just before
the user exits the page.

Tracked data includes:
* 📄 Current URL
* 🔗 Canonical URL, when provided
* 🔀 Symfony route and route arguments
* 📈 UTM campaign data (from URL)
* 🌐 Browser language (navigator.language)
* 🖥️ Screen size (screen.width and screen.height)
* ⏱️ Entry and exit timestamps (tracked via JS)
* 🧩 Optional dimensions explicitly supplied by the application

Dimensions are bounded scalar metadata and are never collected automatically. See [`docs/dimensions.md`](docs/dimensions.md) for accepted values, migration and privacy guidance.

## ⚙️ How it works

1. On page load, a `fetch()` request is sent to the tracking endpoint.
2. The server stores a new PageCall and a new PageCallHit.
3. Stimulus registers visibility, page-hide and Turbo lifecycle listeners once.
4. On page exit or a Turbo page change, `sendBeacon()` closes the hit. A keepalive `fetch()` is used when beacons are unavailable.
5. Returning to a hidden page starts a fresh hit, linked to the previous one when session storage is available.

If JavaScript or `fetch()` is unavailable, the page continues normally and no client-side hit is created.

## Consent integration

The default remains immediate tracking for backward compatibility. Applications can replace a server-side checker and use generic grant/revoke browser events without copying the Twig helper or Stimulus controller:

```javascript
document.dispatchEvent(new CustomEvent('seo-tracking:consent-granted'));
document.dispatchEvent(new CustomEvent('seo-tracking:consent-revoked'));
```

The checker also protects the tracking endpoint; revocation closes an existing hit but does not create a new one or erase historical data. See [`docs/consent.md`](docs/consent.md) for the service contract, configurable event names, CMP integration order and direct Stimulus usage.

## Rate limiting

Public creation and closure endpoints can independently use named Symfony RateLimiter policies. The integration is disabled by default and does not add a runtime dependency unless it is enabled. Rejected requests return HTTP `429` before parsing or database access, and the default bucket key is a SHA-256 hash of Symfony's resolved client IP.

See [`docs/rate-limiting.md`](docs/rate-limiting.md) for installation, configuration, response headers, trusted-proxy guidance and replaceable service contracts.

## Bot classification

The default bot detector now returns an explainable, typed classification while persisting only the existing boolean flag. Applications can replace `BotClassifierInterface`; existing `BotDetectorInterface` replacements remain supported through an adapter. The classification is exposed on `PageCallTrackedEvent` and is never a visitor identity.

See [`docs/bot-classification.md`](docs/bot-classification.md) for default categories, extension contracts, backward compatibility and privacy limitations.

## Invalid request observability

Malformed, unsupported, consent-denied, rate-limited and unknown-hit requests can be sent to a replaceable reporter without creating tracking rows or polluting statistics. The default reporter is a no-op. Its typed event exposes only bounded method/route/content metadata and never forwards the raw payload, IP address or User-Agent.

See [`docs/invalid-events.md`](docs/invalid-events.md) for reason codes, a logger adapter and the fail-closed collection/fail-open reporting behavior.

## ⚠️ Notes & Best Practices

- Only include the stimulus_controller call once per page (usually in your base layout).
- The bundle does not store any cookies or personal identifiers.
- Works well in static pages, Turbo/Stimulus navigation or multi-page apps.
- The defaults minimize collected network data, but the consuming application remains responsible for its legal basis, retention policy and privacy notice.

## 📐 Data model overview

> PageCall groups traffic based on UTM parameters and URL.  
> PageCallHit represents each individual visit or hit within that group.

### PageCall
This entity stores Page calls grouped by their UTM values, with counting calls. It's related to a collection of hits.

* url: URL called
* route: Symfony route called
* routeArgs : Arguments for the called Symfony Route
* campaign: utm_campaign argument received
* medium: utm_medium argument received
* source: utm_source argument received
* term: utm_term argument received
* content: utm_content argument received
* nbCalls: Number of calls with the UTM context
* lastCalledAt: datetime of the last call
* firstCalledAt: datetime of the first call
* hits: related PageCallHits (see below)
* bot: true if this call was made by a bot

### PageCallHit
This entity stores information related to a visit (hit) and is related to a PageCall:

* pageCall: related PageCall
* referrer: URL of the referrer
* userAgent: received User Agent, raw format
* url: actual URL observed for this hit
* anonymizedIP: IP address anonymized to `/24` for IPv4 and `/64` for IPv6 by default
* calledAt: datetime of the call
* exitedAt: datetime of page exit
* durationSeconds: calculated duration of the visit
* language: navigator language (if provided by the navigator)
* screenWidth: screen width in pixels (if provided by the navigator)
* screenHeight: screen height in pixels (if provided by the navigator)
* parentHit: previous PageCallHit if available, useful to reconstruct a visitor flow across multiple pages.
* bot: true if the hit was made by a bot.
* pageTitle: page title, if provided.
* delaySincePreviousHit: delay in seconds between current hit and its parent.
* pageType: page data type, if provided.
* dimensions: optional, application-defined scalar metadata stored as portable JSON.

> Note: `parentHit` does not introduce a persistent identifier; it links consecutive hits when session storage is available. The consuming application must still assess its use under its own privacy policy and legal context.

## 🔁 Listen to PageCallTrackedEvent

The bundle dispatches an event every time a tracked visit is recorded. You can listen to this event in your app using an EventListener
like this example.

```php
use Zhortein\SeoTrackingBundle\Event\PageCallTrackedEvent;

class MyCustomListener
{
    public function __invoke(PageCallTrackedEvent $event): void
    {
        $pageCall = $event->getPageCall();
        $hit = $event->getPageCallHit();
        $classification = $event->getBotClassification();

        // Example: export to your own system
        // or send it to a queue, or just log it
    }
}
```

## 🔎 Symfony Profiler Integration

The SEO Tracking Bundle provides a **dedicated panel** in the Symfony Profiler to help developers visualize **UTM parameters** and **routing metadata** for each tracked request.

### What’s displayed in the profiler:
- The current route name (e.g. `app_homepage`)
- Route parameters (e.g. `{ slug: "example" }`)
- UTM parameters, if present (`utm_campaign`, `utm_source`, `utm_medium`, etc.)

This data helps ensure that campaign tracking is correctly integrated and visible during development.

### ⚠️ Limitations

The Symfony Profiler only reflects **synchronous request-level data**.

Page tracking hits (`PageCallHit`), which are registered via **asynchronous JavaScript calls** (`fetch()` or `navigator.sendBeacon()`), are **not visible in the profiler toolbar**.

For application-facing reports, use the typed [statistics API](docs/statistics.md). The profiler remains limited to the synchronous request.

## 🔁 Customizing Entities via `resolve_target_entities`

By default, the bundle provides two mapped entities: `PageCall` and `PageCallHit`.

However, you may want to extend these entities in your application to store additional information (e.g. link to a `User`, a `Session`, a `Tenant`, etc.).

The bundle supports entity substitution via Symfony’s `resolve_target_entities` mechanism, with zero configuration required.

### ✅ How it works

Internally, the bundle defines two interfaces:

* `Zhortein\SeoTrackingBundle\Entity\PageCallInterface`
* `Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface`

These interfaces are resolved to their corresponding classes by default:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    page_call_class: Zhortein\SeoTrackingBundle\Entity\PageCall
    page_call_hit_class: Zhortein\SeoTrackingBundle\Entity\PageCallHit

```

You can override them by providing your own entity classes:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    page_call_class: App\Entity\MyCustomPageCall
    page_call_hit_class: App\Entity\MyCustomPageCallHit

```

Your custom classes must implement the interfaces:

```php
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

#[ORM\Entity]
class MyCustomPageCall implements PageCallInterface
{
    use \Zhortein\SeoTrackingBundle\Entity\PageCallTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    // your custom fields here
}

```
You can use the provided `PageCallTrait` and `PageCallHitTrait` to avoid duplicating field declarations or missing fields.

The controller uses the configured classes for repository lookup and creation. Classes using a constructor with required arguments can replace the default factories:

```yaml
# config/services.yaml
services:
    App\Seo\PageCallFactory: ~

    Zhortein\SeoTrackingBundle\Tracking\Factory\PageCallFactoryInterface:
        alias: App\Seo\PageCallFactory
```

Implement `PageCallFactoryInterface::create()` and return your configured `PageCallInterface`. The equivalent `PageCallHitFactoryInterface` is available for hits. `TrackingEntityAccessor` can also be replaced when a custom entity deliberately does not expose the historical methods supplied by the bundle traits.

### ⚠️ Notes

* You are not required to declare any resolve_target_entities block in your `doctrine.yaml`.
* The bundle takes care of registering the mapping at runtime via the Symfony Dependency Injection system.
* If you do override the entities, don’t forget to generate and apply a new migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate

```

> For technical details on how this is achieved, see the ZhorteinSeoTrackingExtension class and the use of Symfony's 
> prependExtensionConfig() method.

## IP anonymization

The built-in anonymizer supports IPv4 and IPv6. Prefixes can be configured without replacing the tracking controller:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    anonymization:
        ipv4_prefix: 24
        ipv6_prefix: 64
```

For a different policy, decorate or replace `Zhortein\SeoTrackingBundle\Tracking\Ip\IpAnonymizerInterface`.

## Tracking endpoint URLs

By default, the Twig helper generates URLs from the bundle routes, so an application-level route prefix is respected. Explicit URLs can be configured for a reverse proxy, another host or a custom controller:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    tracking_url: '/analytics/page'
    exit_url: '/analytics/page/exit'
```

The values are passed to Stimulus as `trackingUrl` and `exitUrl`. Applications calling `stimulus_controller()` directly can provide the same values without changing the distributed controller.

## Statistics API and Twig rendering

The bundle exposes typed statistics independently from their presentation. A Bootstrap 5 theme is enabled by default, but Bootstrap is not a runtime dependency:

```twig
{{ seo_tracking_statistics() }}
```

Reports expose typed rankings and exact-match filters for application-defined dimensions without relying on database-specific JSON operators.

For controller-side filters, custom templates, theme disabling and the complete list of deliberately supported metrics, see [`docs/statistics.md`](docs/statistics.md).

## Journey reports

The typed journey API reconstructs bounded path fragments and transition counts from persisted `parentHit` links. It can include an immediate predecessor outside the selected period to preserve boundary context, while clearly marking that step as outside the filter.

These fragments are not visitors, devices or stable sessions. Retention, consent changes and browser session storage can all start or cut a fragment. See [`docs/journeys.md`](docs/journeys.md) for filter semantics, limits, privacy guidance and data-source replacement.

## Historical grouping-key backfill

Applications that upgraded from a version before 1.3 may have historical page calls whose `grouping_key` remains null. Inspect them without changing data:

```bash
php bin/console zhortein:seo-tracking:backfill-grouping-keys
```

Backfill and duplicate consolidation are always explicit. The command is resumable, processes bounded batches and refuses to guess how custom-entity fields should be merged. See [`docs/grouping-backfill.md`](docs/grouping-backfill.md) before using `--apply` or `--merge-duplicates`.

## Tracking-data retention

Retention is opt-in and every purge is a dry-run unless `--apply` is supplied:

```yaml
zhortein_seo_tracking:
    retention:
        days: 180
        batch_size: 500
        remove_empty_page_calls: false
```

```bash
php bin/console zhortein:seo-tracking:purge
```

The purger leaves undated hits untouched and recomputes page-call aggregates from surviving hits. See [`docs/retention.md`](docs/retention.md) for absolute cutoffs, scheduling, empty-group handling, custom entities and rollback.

## Upgrading to 1.5

Update the package with:

```bash
composer require zhortein/seo-tracking-bundle:^1.5
php bin/console asset-map:compile
```

No Doctrine schema migration is required from 1.4. Rate limiting remains disabled, the invalid-event reporter remains a no-op and existing bot-detector replacements remain supported. Read the [1.5 upgrade procedure](docs/upgrade-1.5.md) before enabling endpoint limits or exporting rejected-request telemetry.

Applications upgrading from an older release must first follow the [1.3 schema migration](docs/upgrade-1.3.md).
