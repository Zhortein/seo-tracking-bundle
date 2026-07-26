# Journey reports

Journey reports reconstruct bounded path fragments from the persisted `parentHit` links between hits. They are intended to answer questions such as “which page transitions are observed?” and “which short paths appear in the selected period?”.

They do **not** identify people, devices or unique visitors. The browser stores only the previous hit identifier in its current session storage. Consent revocation clears that value, retention can remove an earlier hit, and browsers can discard session storage at any time.

## PHP usage

Inject `JourneyProviderInterface`:

```php
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;
use Zhortein\SeoTrackingBundle\Journey\JourneyProviderInterface;

final readonly class AnalyticsController
{
    public function __construct(
        private JourneyProviderInterface $journeys,
    ) {
    }

    public function report(): array
    {
        $report = $this->journeys->report(
            new JourneyFilter(
                from: new \DateTimeImmutable('2026-07-01 00:00:00 UTC'),
                to: new \DateTimeImmutable('2026-07-31 23:59:59 UTC'),
                bot: false,
            ),
            transitionLimit: 20,
            pathLimit: 20,
            maxDepth: 25,
        );

        return [
            'summary' => $report->summary,
            'topTransitions' => $report->topTransitions,
            'sampledPaths' => $report->paths,
        ];
    }
}
```

The transition and path limits must be between 1 and 100. Maximum depth must be between 2 and 100. These bounds prevent the generic provider from returning an unbounded entity graph; applications with large datasets can replace the data source or provider.

## Filter and boundary semantics

- `from` and `to` are inclusive and apply to the child hit.
- `bot: null` includes every hit, `false` keeps human hits, and `true` keeps robot hits.
- An immediate parent can be loaded even when it falls outside the filter. It appears as a path step with `withinFilter: false`, and the path sets `hasKnownPredecessorOutsideFilter`.
- A missing `parentHit` means only that no predecessor is currently persisted. It may be a genuine browser-session root, a consent boundary, expired browser state or a link cut by retention.
- Retention sets surviving child links to `null`; the remaining hit consequently starts a new report fragment.
- Malformed cycles and depth overflows are reported through `cyclicPaths` and `truncatedPaths` instead of causing an infinite traversal.

`fragments` counts graph roots visible under these rules. `paths` counts root-to-leaf branches, while `sampledPaths` states how many bounded path DTOs were returned.

## Replaceable data source

The default `DoctrineJourneyDataSource` streams scalar rows from the configured hit entity and supports a single scalar identifier. It works with the bundled traits on SQLite, PostgreSQL and MySQL.

Applications with a different mapping can replace it:

```yaml
services:
    App\Analytics\JourneyDataSource: ~

    Zhortein\SeoTrackingBundle\Journey\DataSource\JourneyDataSourceInterface:
        alias: App\Analytics\JourneyDataSource
```

Return one `JourneyHitObservation` for every hit selected by the filter. When possible, include the immediate parent node even if that parent is outside the filter. The standard provider will keep reconstructing and bounding fragments without depending on Doctrine.

## Privacy and retention

Path fragments can still reveal browsing behavior. Apply the same consent, access-control and retention rules as the underlying hits. Do not combine a fragment with fingerprinting data to manufacture a visitor identity. If an application needs authenticated-user journeys, it should define that separate identity model, lawful basis and access policy explicitly rather than overloading `parentHit`.
