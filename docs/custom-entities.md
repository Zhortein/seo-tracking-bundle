# Custom entities

Applications can replace the bundled `PageCall` and `PageCallHit` classes
without copying the controller. The public marker interfaces are:

```php
Zhortein\SeoTrackingBundle\Entity\PageCallInterface
Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface
```

The bundle registers Doctrine target-entity resolution and uses the configured
classes for repository access, entity creation, statistics, journeys and
retention.

## Reuse the supplied mappings

The simplest custom classes implement the interfaces and use the traits:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallTrait;

#[ORM\Entity]
#[ORM\Table(name: 'app_page_call')]
final class PageCall implements PageCallInterface
{
    use PageCallTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
```

Create the equivalent hit with `PageCallHitInterface` and
`PageCallHitTrait`. The traits include every field expected by the default
collection, statistics, journey and retention services, including the nullable
JSON `dimensions` field.

Configure both classes:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    page_call_class: App\Entity\PageCall
    page_call_hit_class: App\Entity\PageCallHit
```

No application `resolve_target_entities` block is required; the bundle
registers it from this configuration.

Generate and review a Doctrine migration after introducing or changing custom
classes:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
```

## Add application fields deliberately

Extra fields can represent a tenant, site or other application-owned context,
but the generic tracking controller cannot guess their values or lifecycle.
Prefer an event listener for optional enrichment that can happen after
creation. If a field is required before persistence, provide custom factories
or a compatible accessor.

Do not add direct identity merely to manufacture unique-visitor statistics.
Authenticated analytics require an explicit identity model, lawful basis,
authorization and deletion semantics separate from the heuristic
`parentHit` relationship.

## Constructors with required arguments

The default factories instantiate the configured classes without required
constructor arguments. Replace one or both factory contracts when necessary:

```yaml
# config/services.yaml
services:
    App\Analytics\PageCallFactory: ~

    Zhortein\SeoTrackingBundle\Tracking\Factory\PageCallFactoryInterface:
        alias: App\Analytics\PageCallFactory
```

The factory returns a new instance implementing `PageCallInterface`; the
controller then initializes the standard tracking fields. The equivalent
`PageCallHitFactoryInterface` creates hit instances.

If custom classes deliberately omit or rename the trait methods, replace
`TrackingEntityAccessor` with a service that maps the complete collection
contract. A marker interface alone does not make an arbitrary schema
compatible.

## Statistics and journeys

Trait-based entities work with the default Doctrine data sources. A legacy hit
entity without the optional dimensions field still produces historical
statistics, with empty dimension rankings; a dimension filter matches no such
row.

Replace `StatisticsDataSourceInterface` when fields or associations differ:

```yaml
services:
    App\Analytics\StatisticsDataSource: ~

    Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface:
        alias: App\Analytics\StatisticsDataSource
```

The replacement must apply the complete `StatisticsFilter`, including exact,
type-sensitive dimensions, and yield `HitObservation` values. The report
provider, pagination, cache, CSV exporter and Twig presentation can then remain
unchanged.

Replace `JourneyDataSourceInterface` for a different parent mapping or
identifier. Include the immediate predecessor outside the requested date range
when possible so boundary semantics remain visible.

## Retention and historical consolidation

The default purger expects a scalar identifier, a hit `calledAt` field and
`pageCall` association, plus the aggregate count and timestamps. Trait-based
classes satisfy that contract. Otherwise replace `HitRetentionPurgerInterface`.

Historical duplicate consolidation is intentionally refused for custom page
call classes. Extra application fields make automatic survivor selection
unsafe. Implement `DuplicatePageCallMergerInterface` only after defining how
every custom field and association is reconciled. See
[grouping-key backfill](grouping-backfill.md).
