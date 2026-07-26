# Data model and tracking lifecycle

The default Doctrine model separates a grouped page call from each observed
hit. Custom entities can reuse the same traits or replace the model through the
documented contracts.

## Page calls

`PageCall` groups hits by:

- canonical URL when supplied, otherwise the observed URL;
- `utm_campaign`, `utm_medium`, `utm_source`, `utm_term` and `utm_content`;
- the persisted robot boolean.

A deterministic 64-character `groupingKey` provides portable uniqueness even
when UTM values are null. The default entity stores:

| Field | Meaning |
|---|---|
| `url` | Grouping URL. |
| `canonicalUrl` | Explicit canonical URL when one was supplied. |
| `groupingKey` | Portable functional grouping hash. |
| `route`, `routeArgs` | Symfony route context rendered by the Twig helper. |
| `campaign`, `medium`, `source`, `term`, `content` | UTM context. |
| `nbCalls` | Aggregate hit count for the group. |
| `firstCalledAt`, `lastCalledAt` | First and most recent call timestamps. |
| `bot` | Heuristic robot result used in grouping. |
| `idSite` | Legacy integer integration field, defaulting to `0`. |
| `hits` | Related `PageCallHit` collection. |

Historical rows created before 1.3 can have a null grouping key. Use the
[dry-run-first backfill](grouping-backfill.md); do not merge them through an
ad-hoc SQL update.

## Hits

`PageCallHit` represents one tracked page interval:

| Field | Meaning |
|---|---|
| `pageCall` | Owning aggregate. |
| `url` | URL observed in the browser, including a non-canonical variant. |
| `referrer` | Referrer truncated to 2048 characters. |
| `userAgent` | Raw User-Agent truncated to 512 characters. |
| `anonymizedIp` | Masked IPv4 or IPv6 address. |
| `calledAt`, `exitedAt` | Entry and optional exit instants. |
| `durationSeconds` | Calculated duration when a hit is closed; reports retain only non-negative samples. |
| `language` | Browser language supplied by JavaScript. |
| `screenWidth`, `screenHeight` | Browser screen dimensions when available. |
| `parentHit` | Previous hit identifier from the current browser session storage. |
| `delaySincePreviousHit` | Delay from the known parent call. |
| `bot` | Heuristic robot result for the hit. |
| `pageTitle`, `pageType` | Browser title and application-defined generic type. |
| `dimensions` | Nullable, bounded scalar JSON object supplied by the application. |

The bundle does not create a visitor, device or session entity. `parentHit`
links only a best-effort sequence in the current browser session storage and
can be cut by consent, retention, browser behavior or failed requests.

## Browser lifecycle

1. Stimulus connects and sends a JSON creation request when tracking is
   allowed.
2. The endpoint validates the payload, consent and optional rate limit before
   any row is written.
3. The page call is found or created from the functional grouping key.
4. A new hit is stored and its identifier is returned to the browser.
5. The controller stores that identifier in session storage for the next
   parent link.
6. Page hide, Turbo transitions, visibility changes or Stimulus disconnect
   close the current hit through `sendBeacon()` or a keepalive `fetch()`.
7. Repeated closure is idempotent.

When JavaScript or the network is unavailable, navigation continues and no
client-side hit is created. An exit request is best effort, so statistics
distinguish open and closed hits and duration metrics use only usable closed
hits.

## Events

After a successful creation, the bundle dispatches `PageCallTrackedEvent`.
After a successful first closure, it dispatches `PageCallExitEvent`. Both
expose the configured interface instances. The tracked event also exposes the
typed bot classification used for the hit.

An application can enrich its own metrics or queue a separate integration from
these events. Keep slow remote work asynchronous where possible; bundle
collection endpoints are public request paths.

See the [cookbook event example](cookbook.md#react-to-a-tracked-hit).

## Symfony profiler

The development profiler panel shows synchronous request information:

- current Symfony route and route parameters;
- UTM campaign, source, medium, term and content present on that request.

Asynchronous `PageCallHit` creation is a separate request and is not attached
to the original page's toolbar entry. Use the browser Network panel, the
database or the typed [statistics API](statistics.md) to inspect stored hits.
