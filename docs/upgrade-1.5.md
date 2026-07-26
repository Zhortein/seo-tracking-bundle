# Upgrade to 1.5

Version 1.5 protects and explains the public collection path through optional rate limiting, typed bot classification and separate invalid-request observability. It does not change the Doctrine mapping introduced in 1.3.

## Compatibility

The supported runtime remains PHP 8.3 or later, Symfony and AssetMapper 7.3, 7.4 or 8.x, Doctrine DBAL 3 or 4, Doctrine ORM 3.3 or later or 4.x, and Stimulus 3.

Symfony RateLimiter is optional at runtime. Applications that do not enable the integration do not need the component.

## Update

1. Back up the application and tracking tables according to the normal deployment procedure.
2. Update the package:

   ```bash
   composer require zhortein/seo-tracking-bundle:^1.5
   ```

3. Clear the Symfony cache through the application's normal deployment process.
4. Recompile the asset map:

   ```bash
   php bin/console asset-map:compile
   ```

5. Exercise a successful track/exit cycle and one invalid payload in staging.

No Doctrine migration is required when upgrading from 1.4. Applications upgrading from 1.2 or earlier must first apply the [1.3 schema migration](upgrade-1.3.md).

## Backward-compatible defaults

- Hit creation and closure remain unlimited until at least one limiter service ID is configured.
- The invalid-event reporter remains `NullInvalidTrackingEventReporter`.
- The existing `BotDetectorInterface` service contract remains active through `BotDetectorClassifier`.
- Only the historical bot boolean is persisted; classifier, category and identifier values remain event metadata.
- No new cookie, persistent identifier or browser storage is introduced.

The default regex classifier recognizes more common crawler families than 1.4. New requests from those User-Agents may therefore enter the robot grouping rather than the human grouping. Historical rows are not reclassified or rewritten.

## Optional rate limiting

Install Symfony RateLimiter only when adopting this feature:

```bash
composer require symfony/rate-limiter
```

Define named FrameworkBundle policies, then configure their complete `limiter.*` service IDs under:

```yaml
zhortein_seo_tracking:
    rate_limiter:
        creation_limiter: limiter.seo_tracking_creation
        closure_limiter: limiter.seo_tracking_closure
```

The policies are independent. The default resolver hashes `Request::getClientIp()` and relies on the application's trusted-proxy configuration. Review the complete [rate-limiting guide](rate-limiting.md) before production rollout.

When enabled, the limiter runs before consent and JSON parsing. Invalid and consent-denied requests therefore consume creation-policy tokens as part of protecting the public endpoint.

## Bot classifier migration

New integrations should replace `BotClassifierInterface`. Existing applications that replace only `BotDetectorInterface` continue to work and receive the compatibility classification `legacy-detector` / `unknown`.

If an event listener constructs `PageCallTrackedEvent` itself, its existing two-argument construction remains valid. `getBotClassification()` returns `null` for those legacy events.

Treat all classifications as heuristics. They are not proof that a request came from a person or a robot and must not be used as a visitor identity.

## Invalid-event reporter

The default reporter discards rejected-request events. To adopt logging or metrics, replace `InvalidTrackingEventReporterInterface` after reviewing [the reason codes and safe metadata contract](invalid-events.md).

Prefer aggregated counters or sampled logs. Rejected requests are attacker-controlled traffic and must not create an unbounded secondary event store.

Collection is fail-closed while reporting is fail-open: a rejected request never creates a hit, but an exception from the custom reporter is suppressed so the original public rejection remains unchanged.

## Verification

In staging, verify:

1. a valid browser hit and exit still return HTTP 200;
2. a configured creation limiter returns HTTP 429 and creates no row after its budget is exhausted;
3. the closure policy is independent from the creation policy;
4. an expected crawler is persisted with `bot = true`;
5. a custom classifier or legacy detector replacement still controls the persisted boolean;
6. malformed and consent-denied requests reach a configured invalid-event reporter without raw payload or network data;
7. a reporter failure does not change the rejection response.

## Rollback

Downgrading the code to 1.4 requires no schema rollback.

- Remove the `rate_limiter` bundle configuration before deploying 1.4. Named FrameworkBundle limiters may remain unused and their cached tokens can expire naturally.
- Remove aliases for `BotClassifierInterface` and `InvalidTrackingEventReporterInterface`.
- External logs or counters created by a custom reporter are outside the bundle and follow their own retention policy.
- Hits classified by 1.5 retain their persisted bot boolean; no automatic historical reclassification is attempted.

Restore the previous application code and clear its cache using the normal deployment process.
