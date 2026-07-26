# Tracking endpoint rate limiting

Rate limiting is disabled by default. Enabling it requires the optional Symfony component:

```bash
composer require symfony/rate-limiter
```

Define one or two named limiters in FrameworkBundle, then give the bundle their complete service IDs:

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        seo_tracking_creation:
            policy: sliding_window
            limit: 60
            interval: '1 minute'
        seo_tracking_closure:
            policy: fixed_window
            limit: 120
            interval: '1 minute'

# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    rate_limiter:
        creation_limiter: limiter.seo_tracking_creation
        closure_limiter: limiter.seo_tracking_closure
```

Each option is independent and nullable. Configuring only `creation_limiter` protects new hits while existing hits can still be closed without consuming a rate-limit token. Configuring only `closure_limiter` does the inverse.

Rejected requests return HTTP `429` before JSON parsing, consent evaluation or database access. The response includes `X-RateLimit-Limit`, `X-RateLimit-Remaining` and an HTTP-date `Retry-After` header when the configured limiter supplies those values. A rejected creation never creates or updates a `PageCall` or `PageCallHit`.

## Keys and trusted proxies

The default `ClientIpTrackingRateLimitKeyResolver` uses Symfony's `Request::getClientIp()` and hashes the result with SHA-256 before giving it to the limiter. Raw IP addresses are therefore not used as limiter keys. Configure Symfony trusted proxies correctly; otherwise the connection address, rather than the original client address, will identify the bucket.

Requests for which Symfony cannot resolve an address share a hashed `unknown` bucket. Applications that authenticate callers, serve several tenants or require a different partition can replace the resolver:

```yaml
services:
    App\Analytics\TrackingRateLimitKeyResolver: ~

    Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingRateLimitKeyResolverInterface:
        alias: App\Analytics\TrackingRateLimitKeyResolver
```

The custom service implements:

```php
use Symfony\Component\HttpFoundation\Request;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingRateLimitKeyResolverInterface;

final class TrackingRateLimitKeyResolver implements TrackingRateLimitKeyResolverInterface
{
    public function resolve(Request $request, TrackingEndpoint $endpoint): string
    {
        // Return a stable, non-sensitive bucket key.
    }
}
```

Do not use a raw email address, account identifier or other personal value as the key. Hash or pseudonymize application identifiers according to the consuming application's privacy policy.

## Replacing the complete policy

For a policy that is not backed by Symfony RateLimiter, replace `TrackingRateLimiterInterface`. Its `consume()` method receives the request and either `TrackingEndpoint::CREATION` or `TrackingEndpoint::CLOSURE`, and returns a `TrackingRateLimitDecision`.

The bundle deliberately does not catch policy or storage failures. An unexpected limiter failure therefore produces the application's normal server error instead of silently bypassing a configured protection.
