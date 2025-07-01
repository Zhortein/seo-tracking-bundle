# seo-tracking-bundle
Symfony bundle to track page views, UTM campaigns and basic engagement, with optional SEO insights integration.

## 📦 Installation

```bash
composer require zhortein/seo-tracking-bundle
```

Symfony 6.3+ and 7.x are fully supported.

If you're not using Symfony Flex, enable the bundle manually in config/bundles.php:

```php
Zhortein\SeoTrackingBundle\SeoTrackingBundle::class => ['all' => true],
```

## ⚙️ Usage
To enable tracking, include the Stimulus controller in your layout or any page you want to track:

```twig
<body {{ stimulus_controller('zhortein--seo-tracking-bundle--tracking', {
        route: app.request.attributes.get('_route'),
        routeArgs: app.request.attributes.get('_route_params')
    }) }}
```

We recommend placing the tracking call as early as possible in your page. The ```<body>``` tag is a good place for this.

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

### PageCall
This entity store Page calls grouped by their UTM values, with counting calls. It's related to a collection of hits.

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

### PageCallHit
This entity stores information related to a visit (hit) and is related to a PageCall:

* pageCall: related PageCall
* referrer: URL of the referrer
* userAgent: received USer Agent, raw format
* anonymizedIP: IP address of the visitor anonymized (GDPR compliance)
* calledAt: datetime of the call
* exitedAt: datetime of page exit
* durationSeconds: calculated duration of the visit
* language: navigator language (if provided by the navigator)
* screenWidth: screen width in pixels (if provided by the navigator)
* screenHeight: screen height in pixels (if provided by the navigator)

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

