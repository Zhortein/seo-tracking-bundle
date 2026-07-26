# SEO Tracking Bundle

A privacy-conscious Symfony bundle for page-view, campaign and engagement
tracking. It provides a tested Stimulus lifecycle, portable Doctrine storage,
typed statistics and journey APIs, safe CSV exports, and overrideable Twig
presentation.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zhortein/seo-tracking-bundle.svg)](https://packagist.org/packages/zhortein/seo-tracking-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/zhortein/seo-tracking-bundle.svg)](https://packagist.org/packages/zhortein/seo-tracking-bundle)
[![License](https://img.shields.io/packagist/l/zhortein/seo-tracking-bundle.svg)](https://github.com/Zhortein/seo-tracking-bundle/blob/main/LICENSE)

## What it provides

- asynchronous page-view and exit tracking for classic, Turbo and
  visibility-change navigation;
- canonical URL and UTM grouping with portable behavior on supported
  databases;
- IPv4 and IPv6 anonymization with configurable prefixes;
- optional consent gates, endpoint rate limiting and invalid-request
  reporting;
- explainable bot classification without presenting it as visitor identity;
- bounded, application-defined scalar dimensions;
- typed statistics, journey fragments and observation pagination;
- opt-in PSR-6 report caching;
- lazy, spreadsheet-safe CSV exports;
- Bootstrap 5 and dependency-free semantic HTML5 statistics themes;
- replaceable entities, factories, data sources, policies and templates.

The bundle deliberately does not claim unique visitors, devices or stable
sessions. It creates no tracking cookie or persistent visitor identifier.
Collected URLs, User-Agent values, anonymized network data and
application-defined dimensions can still require a legal basis, access
controls, retention rules and privacy information in the host application.

## Compatibility

| Layer | Supported versions |
|---|---|
| PHP | 8.3+ |
| Symfony and AssetMapper | 7.3, 7.4 and 8.x |
| Doctrine DBAL | 3.x and 4.x |
| DoctrineBundle | 2.15+ and 3.2+ |
| Doctrine ORM | 3.3+ and 4.x |
| Stimulus | 3.x |

CI exercises PHP 8.3–8.5, Symfony 7.3–8.1, Node 22/24, SQLite,
PostgreSQL 16 and MySQL 8.4. Other Doctrine platforms may work through the
portable abstractions, but they are not part of the published CI guarantee.

## Quick start

Install the bundle:

```bash
composer require zhortein/seo-tracking-bundle
```

Generate, review and apply the Doctrine migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

Enable tracking once in the application layout:

```twig
<body {{ seo_tracking('generic') }}>
    {# application layout #}
</body>
```

Compile production assets:

```bash
php bin/console asset-map:compile
```

The [complete quick start](docs/quick-start.md) covers bundle and route
registration, Stimulus verification, canonical URLs, safe dimensions, the
first stored hit and a secured statistics page.

## Minimal examples

Track an article using its canonical URL and controlled business dimensions:

```twig
<body {{ seo_tracking('article', article.canonicalUrl, {
    content_category: article.category.slug,
    subscriber: app.user is not null
}) }}>
```

Render an all-time statistics report using the configured theme:

```twig
{{ seo_tracking_statistics() }}
```

Build a filtered report in PHP:

```php
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;

$filter = new StatisticsFilter(
    from: new \DateTimeImmutable('-30 days'),
    timezone: new \DateTimeZone('Europe/Paris'),
    bot: false,
    pageType: 'article',
);

$report = $statistics->report($filter, limit: 20);
```

Statistics and row-level exports contain operational information. Expose them
only through application routes protected by appropriate authorization.
Ready-to-adapt controllers and templates are available in the
[application cookbook](docs/cookbook.md).

## Documentation

Start with the [documentation index](docs/index.md).

| Need | Guide |
|---|---|
| Install and record the first hit | [Quick start](docs/quick-start.md) |
| See every configuration option and default | [Configuration reference](docs/configuration.md) |
| Copy common application integrations | [Cookbook](docs/cookbook.md) |
| Understand stored fields and grouping | [Data model](docs/data-model.md) |
| Replace the bundled entities | [Custom entities](docs/custom-entities.md) |
| Build reports, pagination, cache and exports | [Statistics API](docs/statistics.md) |
| Understand privacy and consent boundaries | [Consent](docs/consent.md) and [retention](docs/retention.md) |
| Upgrade an existing application | [Upgrade guides](docs/index.md#upgrades-and-releases) |

## Upgrading

The latest release is 1.7:

```bash
composer require zhortein/seo-tracking-bundle:^1.7
php bin/console asset-map:compile
```

Version 1.7 requires no Doctrine migration and keeps report caching disabled by
default. Applications upgrading from an older release must still apply the
schema changes introduced in 1.3 and 1.6 where applicable. Follow the
[1.7 upgrade and rollback guide](docs/upgrade-1.7.md) and the
[changelog](CHANGELOG.md).

## Contributing

Bug reports and proposals are welcome. Read
[CONTRIBUTING.md](CONTRIBUTING.md) for the `develop`-based workflow, local
checks and documentation requirements. Exploratory work that is not attached
to a milestone remains in [FEATURE_IDEAS.md](FEATURE_IDEAS.md).

## License

This bundle is available under the [MIT License](LICENSE).
