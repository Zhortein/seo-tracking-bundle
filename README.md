# SEO Tracking Bundle
Symfony bundle to track page views, UTM campaigns and basic engagement, with optional SEO insights integration.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zhortein/seo-tracking-bundle.svg)](https://packagist.org/packages/zhortein/seo-tracking-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/zhortein/seo-tracking-bundle.svg)](https://packagist.org/packages/zhortein/seo-tracking-bundle)
[![License](https://img.shields.io/packagist/l/zhortein/seo-tracking-bundle.svg)](https://github.com/Zhortein/seo-tracking-bundle/blob/main/LICENSE)

## 📦 Installation

```bash
composer require zhortein/seo-tracking-bundle
```

Symfony 6.3+ and 7.x are fully supported.

If you're not using Symfony Flex, enable the bundle manually in config/bundles.php:

```php
Zhortein\SeoTrackingBundle\SeoTrackingBundle::class => ['all' => true],
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

The bundle automatically collects basic visit tracking data using Stimulus and <code>fetch()</code> calls, without setting cookies 
or requiring consent (GDPR-friendly by default). Data is sent asynchronously when a page is loaded and just before 
the user exits the page.

Tracked data includes:
* 📄 Current URL
* 🔀 Symfony route and route arguments
* 📈 UTM campaign data (from URL)
* 🌐 Browser language (navigator.language)
* 🖥️ Screen size (screen.width and screen.height)
* ⏱️ Entry and exit timestamps (tracked via JS)

## ⚙️ How it works

1. On page load, a fetch() request is sent to the tracking endpoint.
2. The server stores a new PageCall and a new PageCallHit.
3. A listener is added to the page to detect page exit.
4. On page unload (tab close, navigation), a fetch() request is sent to update the exitedAt timestamp and calculate the duration.

## ⚠️ Notes & Best Practices

- Only include the stimulus_controller call once per page (usually in your base layout).
- The bundle does not store any cookies or personal identifiers.
- Works well in static pages, Turbo/Stimulus navigation or multi-page apps.
- Fully GDPR-compliant by design (but double-check based on your legal context).

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
* anonymizedIP: IP address of the visitor anonymized (GDPR compliance)
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

> Note: `parentHit` does not identify users, it only links anonymous visits together. It is designed to remain GDPR-compliant when used properly.

## 🔁 Listen to PageCallTrackedEvent

The bundle dispatches an event every time a tracked visit is recorded. You can listen to this event in your app using an EventListener
like this example.

```php
use ZhorTein\SeoTrackingBundle\Event\PageCallTrackedEvent;

class MyCustomListener
{
    public function __invoke(PageCallTrackedEvent $event): void
    {
        $pageCall = $event->pageCall;
        $hit = $event->pageCallHit;

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

> If you need to debug or analyze `PageCallHit` records, refer to your database directly or use the dedicated interface provided by the future companion tool (under development).

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
    use \Zhortein\SeoTrackingBundle\Entity\Traits\PageCallTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    // your custom fields here
}

```
You can use the provided `PageCallTrait` and `PageCallHitTrait` to avoid duplicating field declarations or missing fields.

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


