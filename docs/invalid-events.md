# Invalid tracking event observability

Rejected tracking requests are not hits and must not be mixed into SEO statistics. Version 1.5 exposes them through a separate, replaceable reporter that is a no-op by default.

`InvalidTrackingEventReason` provides stable reason codes:

| Reason | Meaning |
|---|---|
| `malformed_json` | The body is not valid JSON. |
| `unsupported_payload` | JSON is valid, but its root is not an object. |
| `invalid_payload` | The object is missing required fields or contains invalid values. |
| `consent_denied` | The application consent checker rejected hit creation. |
| `rate_limited` | The configured creation or closure limiter rejected the request. |
| `unknown_hit` | A syntactically valid closure references no known hit. |

Successful tracking and idempotent closure do not call the reporter. Database failures are operational failures, not invalid client events, and are not sent to this contract.

## Safe event context

The reporter receives only:

- the typed reason and creation/closure endpoint;
- an occurrence timestamp;
- the HTTP method, truncated to 16 characters;
- the Symfony route name when available, truncated to 128 characters;
- the Content-Type header, truncated to 128 characters;
- the declared Content-Length as a nullable integer, capped at 10,000,000.

It never reads or receives the raw body, URL, query string, route arguments, IP address, referrer or User-Agent. The factory intentionally does not expose an open metadata array, so later request values cannot be added accidentally without changing and reviewing the public contract. A missing, malformed or very large Content-Length is represented by `null` or the documented cap; it is telemetry, not a trusted validation value.

The values are safe defaults, not a complete legal conclusion. A custom reporter decides where events are stored and must define its own retention, access control and aggregation policy.

## Replacing the no-op reporter

Implement `InvalidTrackingEventReporterInterface`:

```php
use Psr\Log\LoggerInterface;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEvent;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReporterInterface;

final readonly class InvalidTrackingEventLogger implements InvalidTrackingEventReporterInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function report(InvalidTrackingEvent $event): void
    {
        $this->logger->notice('Rejected SEO tracking request', [
            'reason' => $event->reason->value,
            'endpoint' => $event->endpoint->value,
            'route' => $event->route,
            'content_type' => $event->contentType,
            'content_length' => $event->contentLength,
        ]);
    }
}
```

Register the alias:

```yaml
services:
    App\Analytics\InvalidTrackingEventLogger: ~

    Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReporterInterface:
        alias: App\Analytics\InvalidTrackingEventLogger
```

Prefer counters or sampled logs over one durable record per rejected request. An attacker can deliberately produce invalid or rate-limited traffic, so an unbounded reporter could become its own denial-of-service or storage problem.

## Failure semantics

Collection remains fail-closed for every rejection: an invalid, denied or limited request never creates tracking data.

Reporting is fail-open for the response path: if the custom reporter or event factory throws, the bundle suppresses that exception and returns the original `400`, `403`, `404` or `429` response. Reporter details are never returned to the public client. A reporting outage therefore loses invalid-event telemetry but cannot silently accept a rejected hit or turn it into an information-leaking `500`.

If operational alerting for reporter failures is required, implement it inside the reporter without rethrowing, or supervise the reporter's external transport independently.
