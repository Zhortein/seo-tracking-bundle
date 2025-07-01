# ✨ Feature Ideas for SeoTrackingBundle

This file lists potential improvements and optional features that could be added to the bundle in future versions.

> 💡 Feel free to contribute or open issues if you have ideas or suggestions!

---

## ✅ Validated ideas to consider implementing

### 1. Ignore bot traffic
Skip tracking for known bots (Googlebot, Bingbot...) based on the User-Agent.
```php
if (preg_match('/bot|crawl|slurp|spider/i', $userAgent)) {
    return new JsonResponse(['status' => 'ignored - bot']);
}
```

### 2. Truncate long strings
To avoid potential DB errors, truncate referer, userAgent, UTM values, etc. to a reasonable size (e.g. 255 or 512 chars).

### 3. Sanitize input values
Add a helper like:

```php
function sanitize(?string $value, int $maxLength = 255): ?string
```
Then use it for campaign, term, medium, etc.

### 4. Rate limiting
Add a [Symfony RateLimiter](https://symfony.com/doc/current/rate_limiter.html) to /page-call/track endpoint to prevent abuse or unexpected flood of requests.

### 5. Extract tracking logic into a dedicated service `#refacto` `#priority-high`
Move logic from controller into a PageCallTracker service to ease unit testing and future reuse.

### 6. Add support for tracking errors or invalid events
Allow logging or tracking of broken pages, 404s, or user misclicks via a custom endpoint.

### 7. Add a CLI tool to clean old hits
A Symfony Command to purge hits older than X months, or hits without exit timestamps.

### 8. Add a lightweight dashboard or API
Expose a simple admin route or API to visualize aggregated stats (nbCalls, exits, duration, referrers...).

## ❓ Under consideration

### 🔐 GDPR: add optional consent handling
Require explicit frontend opt-in before tracking starts. Could integrate with existing cookie banner solutions.

### 🧠 Enhanced visitor flow tracking
Use parentHitId to build visit trees or paths. Consider how far this should go (e.g. depth limit, expiration, session-based grouping...).

### 📦 Support PostgreSQL JSON for extra metrics
Allow developers to store extra info (e.g. A/B test variation, user context...) in a JSON field.

## 🚫 Rejected ideas (for now)
(none yet)

## 🗓️ Last update
Generated on: 2025-07-01