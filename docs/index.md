# Documentation

This index describes the public behavior of SEO Tracking Bundle 1.7. Start
with the quick guide for a new application, then use the topic guides as
reference material.

## Getting started

- [Quick start](quick-start.md): install the package, create the schema, enable
  tracking, verify the first hit and render a secured report.
- [Configuration reference](configuration.md): every configuration key,
  default, validation rule and replaceable service.
- [Application cookbook](cookbook.md): ready-to-adapt tracking, reporting,
  pagination, export, journey and event-listener examples.
- [Data model](data-model.md): persisted fields, grouping rules, browser
  lifecycle and profiler behavior.
- [Custom entities](custom-entities.md): replace `PageCall` and `PageCallHit`
  safely, including factory and data-source considerations.

## Collection, privacy and protection

- [Consent integration](consent.md)
- [Custom hit dimensions](dimensions.md)
- [Bot classification](bot-classification.md)
- [Tracking endpoint rate limiting](rate-limiting.md)
- [Invalid tracking event observability](invalid-events.md)
- [Easylyse forwarding](easylyse-forwarding.md)

## Reports and presentation

- [Statistics API](statistics.md)
- [Statistics pagination and cache](statistics-performance.md)
- [Streaming statistics exports](statistics-export.md)
- [Accessible statistics presentation](statistics-presentation.md)
- [Journey reports](journeys.md)

## Data lifecycle and operations

- [Tracking-data retention](retention.md)
- [Historical grouping-key backfill](grouping-backfill.md)

## Upgrades and releases

Always read every upgrade guide between the installed version and the target
version. Schema changes are intentionally not repeated in later guides.

| Version | Upgrade guide | Release notes |
|---|---|---|
| 1.7 | [Upgrade to 1.7](upgrade-1.7.md) | [1.7.2](releases/1.7.2.md), [1.7.1](releases/1.7.1.md), [1.7.0](releases/1.7.0.md) |
| 1.6 | [Upgrade to 1.6](upgrade-1.6.md) | [1.6.0](releases/1.6.0.md) |
| 1.5 | [Upgrade to 1.5](upgrade-1.5.md) | [1.5.0](releases/1.5.0.md) |
| 1.4 | [Upgrade to 1.4](upgrade-1.4.md) | [1.4.0](releases/1.4.0.md) |
| 1.3 | [Upgrade to 1.3](upgrade-1.3.md) | [1.3.0](releases/1.3.0.md) |

See the repository [changelog](../CHANGELOG.md) for earlier releases and
[roadmap](../FEATURE_IDEAS.md) for deliberately deferred work.

## Contributing to the documentation

Documentation is written in English and must describe generic bundle behavior,
not a consuming application. Public examples must use supported APIs and keep
authorization, consent, privacy and destructive operations explicit. See the
[contribution guide](../CONTRIBUTING.md) for the checks run by CI.
