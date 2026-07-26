# Quick start

This guide installs SEO Tracking Bundle 1.7 in a conventional Symfony
application using Doctrine ORM, Twig, AssetMapper and Stimulus. It finishes
with a real browser request, a stored hit and a protected statistics page.

## 1. Check the runtime

The application needs PHP 8.3 or later, a supported Symfony/AssetMapper
version, Doctrine ORM and Stimulus 3. See the current
[compatibility matrix](../README.md#compatibility).

Install the bundle:

```bash
composer require zhortein/seo-tracking-bundle
```

Symfony Flex normally enables the bundle. Without Flex, add it manually:

```php
// config/bundles.php
return [
    // ...
    Zhortein\SeoTrackingBundle\ZhorteinSeoTrackingBundle::class => ['all' => true],
];
```

The distributed controller uses Stimulus. If the application does not already
use Symfony StimulusBundle, install and initialize it according to the normal
Symfony recipe before continuing:

```bash
composer require symfony/stimulus-bundle
```

## 2. Verify the tracking routes

The bundle creates the conventional route import when its container extension
is first loaded. Verify that the application contains:

```yaml
# config/routes/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    resource: '@ZhorteinSeoTrackingBundle/config/routes.yaml'
```

Add that file manually if the application prevents packages from writing
configuration or if the import is absent. Then check both POST routes:

```bash
php bin/console debug:router seo_tracking_page_call
php bin/console debug:router seo_tracking_page_exit
```

Their default paths are:

```text
/zhortein/seo-tracking/page-call/track
/zhortein/seo-tracking/page-call/exit
```

An application route prefix is respected because `seo_tracking()` generates
the route URLs. A reverse proxy or custom endpoint can instead use the
[`tracking_url` and `exit_url` options](configuration.md#endpoint-urls).

## 3. Create the database schema

Generate an application migration:

```bash
php bin/console make:migration
```

Review the generated SQL before applying it. A fresh installation using the
default entities creates `seo_page_call` and `seo_page_call_hit`, their foreign
keys, the grouping-key index and uniqueness constraint, and the nullable JSON
`dimensions` column.

Apply the migration and validate the mapping:

```bash
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
```

If MakerBundle is not installed, create the migration through the
application's normal Doctrine migration workflow. Do not use
`doctrine:schema:update --force` as a production deployment procedure.

## 4. Enable tracking once

Place the Twig helper on the layout's `<body>` element:

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html lang="{{ app.request.locale }}">
    <head>
        <meta charset="UTF-8">
        <title>{% block title %}Application{% endblock %}</title>
        <link rel="canonical" href="{{ app.request.uri }}">
        {% block importmap %}{{ importmap('app') }}{% endblock %}
    </head>
    <body {{ seo_tracking('generic') }}>
        {% block body %}{% endblock %}
    </body>
</html>
```

Use the helper only once on a page. Its first argument is a generic page type
chosen by the application. The optional second argument is an explicit
canonical URL:

```twig
<body {{ seo_tracking('article', article.canonicalUrl) }}>
```

When no explicit canonical URL is supplied, the controller reads
`<link rel="canonical">`. If neither exists, the observed URL is still stored
and used for grouping.

The optional third argument contains bounded scalar dimensions:

```twig
<body {{ seo_tracking('article', article.canonicalUrl, {
    content_category: article.category.slug,
    subscriber: app.user is not null
}) }}>
```

Never place an email address, account identifier, raw user input, IP address or
fingerprinting value in a dimension. Read the complete
[dimension contract](dimensions.md) before adding business metadata.

## 5. Build and verify the controller

In production, compile the asset map:

```bash
php bin/console asset-map:compile
```

Useful development checks are:

```bash
php bin/console debug:stimulus
php bin/console debug:asset-map
```

Open a tracked page in a browser. The developer tools Network panel should show
a successful JSON `POST` to `seo_tracking_page_call`. Leaving the page,
navigating with Turbo or hiding it should send the exit request. Verify the
database through an application query or SQL:

```sql
SELECT id, url, nb_calls, first_called_at, last_called_at
FROM seo_page_call
ORDER BY id DESC;

SELECT id, page_call_id, called_at, exited_at, duration_seconds, page_type
FROM seo_page_call_hit
ORDER BY id DESC;
```

An open page legitimately has a null `exited_at` and duration until its exit is
recorded. Browser privacy settings, network interruption or JavaScript failure
can also leave a hit open; duration metrics deliberately exclude it.

## 6. Render a first secured report

The bundle provides no public dashboard route. Add statistics to an
application-controlled admin page and protect it with the application's normal
authorization:

```php
<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SeoStatisticsController extends AbstractController
{
    #[Route('/admin/seo-statistics', name: 'admin_seo_statistics', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/seo_statistics.html.twig');
    }
}
```

```twig
{# templates/admin/seo_statistics.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}SEO statistics{% endblock %}

{% block body %}
    <h1>SEO statistics</h1>
    {{ seo_tracking_statistics() }}
{% endblock %}
```

Bootstrap 5 class names are emitted by default, but Bootstrap is not installed
by the bundle. Applications without Bootstrap can select the dependency-free
HTML5 theme:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    statistics:
        theme: html5
```

## 7. Choose the next guide

- Add filters, custom rendering or dimension rankings:
  [statistics API](statistics.md).
- Add a consent gate: [consent integration](consent.md).
- Protect public endpoints: [rate limiting](rate-limiting.md).
- Add retention before production accumulation:
  [tracking-data retention](retention.md).
- Copy application controllers for reports, pagination and export:
  [cookbook](cookbook.md).
- Replace entities or services:
  [custom entities](custom-entities.md) and
  [configuration reference](configuration.md).

## Troubleshooting

### No tracking request appears

Check that `seo_tracking()` is present once, `importmap('app')` loads the
application JavaScript, and `debug:stimulus` lists the distributed controller.
Rebuild the asset map after an upgrade. Browser console warnings beginning with
`SEO tracking` describe client-side failures without breaking page navigation.

### The tracking request returns 404

Run `debug:router` for both route names and add the route import from step 2.
If a route prefix or reverse proxy is involved, inspect the URLs generated on
the body element and configure explicit endpoint URLs only when necessary.

### The tracking request returns 500

Run `doctrine:schema:validate` and compare the application migration history
with the [upgrade guides](index.md#upgrades-and-releases). A default or
trait-based 1.6+ hit entity requires the nullable JSON `dimensions` column.

### Statistics are empty

Confirm that hits exist, the selected dates include their `called_at` values
and the robot/page-type/dimension filters match. Dimension matching is exact
and type-sensitive. A custom hit entity that does not expose historical fields
may need a custom statistics data source.
