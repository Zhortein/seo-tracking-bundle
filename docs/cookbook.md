# Application cookbook

These recipes use the public 1.7 APIs and are intended to be adapted inside a
consuming Symfony application. They do not create public dashboard or export
routes automatically; authorization remains application-owned.

## Track a content page

If the application does not already track from its base layout, use one helper
on the page and pass only controlled metadata:

```twig
{# templates/article/show.html.twig #}
<article {{ seo_tracking('article', article.canonicalUrl, {
    content_category: article.category.slug,
    publication_kind: article.kind.value
}) }}>
    <h1>{{ article.title }}</h1>
    {# ... #}
</article>
```

If the base layout already carries `seo_tracking()`, pass page-specific values
to that single call through Twig variables instead of adding a second
controller:

```twig
{# templates/base.html.twig #}
<body {{ seo_tracking(
    tracking_type|default('generic'),
    tracking_canonical_url|default(null),
    tracking_dimensions|default({})
) }}>
```

```twig
{# templates/article/show.html.twig #}
{% extends 'base.html.twig' %}

{% set tracking_type = 'article' %}
{% set tracking_canonical_url = article.canonicalUrl %}
{% set tracking_dimensions = {
    content_category: article.category.slug
} %}
```

## Render a filtered admin report

Build the filter in a protected controller:

```php
<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;

#[IsGranted('ROLE_ADMIN')]
final class SeoStatisticsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsProviderInterface $statistics,
    ) {
    }

    #[Route('/admin/seo-statistics', name: 'admin_seo_statistics', methods: ['GET'])]
    public function __invoke(): Response
    {
        $filter = new StatisticsFilter(
            from: new \DateTimeImmutable('-30 days'),
            to: new \DateTimeImmutable('now'),
            timezone: new \DateTimeZone('Europe/Paris'),
            bot: false,
            pageType: 'article',
        );

        return $this->render('admin/seo_statistics.html.twig', [
            'report' => $this->statistics->report($filter, limit: 20),
        ]);
    }
}
```

Render the typed report through the configured theme:

```twig
{# templates/admin/seo_statistics.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
    <h1>SEO statistics</h1>
    {{ seo_tracking_statistics(report) }}
{% endblock %}
```

For user-selected dates, validate the input format before constructing
`DateTimeImmutable`. `StatisticsFilter` rejects a start instant after the end
instant. Keep the selected timezone explicit so daily grouping and displayed
dates agree.

## Filter by exact dimensions

The stored scalar type is part of the match:

```php
$filter = new StatisticsFilter(
    from: new \DateTimeImmutable('-7 days'),
    bot: false,
    dimensions: [
        'plan' => 'professional',
        'authenticated' => true,
    ],
);

$report = $statistics->report($filter);
```

All supplied dimensions must match. The boolean `true`, integer `1`, float
`1.0` and string `"1"` are different values.

## Browse observations with bounded memory

```php
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationBrowserInterface;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationPageRequest;

$page = $observations->page(
    new StatisticsFilter(
        from: new \DateTimeImmutable('-30 days'),
        bot: false,
    ),
    new ObservationPageRequest(offset: $offset, limit: 50),
);

return [
    'items' => $page->items,
    'nextOffset' => $page->nextOffset(),
    'hasMore' => $page->hasMore,
];
```

Validate an offset received from the request before constructing
`ObservationPageRequest`. The built-in implementation caps a page at 500 rows
and uses offset semantics; see
[pagination performance boundaries](statistics-performance.md).

## Stream a protected CSV

The full [streaming CSV guide](statistics-export.md) contains a complete
`StreamedResponse` controller. Its essential loop is:

```php
$response = new StreamedResponse(function () use ($filter, $options): void {
    foreach ($this->exporter->export($filter, $options) as $chunk) {
        echo $chunk;
        flush();
    }
});
```

Do not call `iterator_to_array()` for a large export. Set safe download headers,
restrict the date range, protect the route and audit access according to the
application's data policy.

## Report bounded journey fragments

```php
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;
use Zhortein\SeoTrackingBundle\Journey\JourneyProviderInterface;

$report = $journeys->report(
    new JourneyFilter(
        from: new \DateTimeImmutable('-30 days'),
        to: new \DateTimeImmutable('now'),
        bot: false,
    ),
    transitionLimit: 20,
    pathLimit: 20,
    maxDepth: 25,
);

return [
    'summary' => $report->summary,
    'transitions' => $report->topTransitions,
    'paths' => $report->paths,
];
```

These paths are browser fragments created from `parentHit`, not visitors or
stable sessions. Read the [journey semantics](journeys.md) before labeling or
exposing them.

## React to a tracked hit

Use an event listener for application-owned enrichment or asynchronous
integration:

```php
<?php

namespace App\Analytics;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Zhortein\SeoTrackingBundle\Event\PageCallTrackedEvent;

#[AsEventListener]
final readonly class QueueTrackedHit
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(PageCallTrackedEvent $event): void
    {
        $hit = $event->getPageCallHit();
        $classification = $event->getBotClassification();

        // Map only the bounded values required by the application message.
        // Do not serialize Doctrine entities directly into an async transport.
    }
}
```

`PageCallExitEvent` exposes the page call and the newly closed hit. Remote work
should normally be queued so public tracking latency does not depend on a
third-party service.

## Gate tracking on application consent

Replace `TrackingConsentCheckerInterface` so both Twig rendering and the
creation endpoint use the same application decision, persist the decision
before dispatching the grant browser event, and clear it before dispatching
revocation. The complete implementation and event order are in
[consent integration](consent.md).

## Change presentation without changing metrics

Use semantic HTML without a frontend framework:

```yaml
zhortein_seo_tracking:
    statistics:
        theme: html5
```

Or configure an application template:

```yaml
zhortein_seo_tracking:
    statistics:
        template: 'admin/analytics/report.html.twig'
```

Every template receives a `StatisticsReport` variable named `report`.
Preserve exact textual counts and a tabular equivalent when replacing
visualizations. See [presentation overrides](statistics-presentation.md).
