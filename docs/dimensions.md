# Custom hit dimensions

Custom dimensions attach explicit, application-defined business context to a hit without changing `PageCall` grouping. Typical examples are a public tenant code, content category, plan name, experiment variant or authenticated role.

The bundle never derives dimensions automatically. The consuming application chooses every key and value and remains responsible for consent, data minimisation, access control, retention and its privacy notice.

## Twig helper

Pass dimensions as the third argument:

```twig
<div {{ seo_tracking('article', article.canonicalUrl, {
    content_category: article.category.slug,
    plan: current_plan_code,
    authenticated: app.user is not null
}) }}></div>
```

The generated Stimulus value is sent with every hit created by that controller instance.

## Direct Stimulus usage

```twig
<body {{ stimulus_controller('zhortein--seo-tracking-bundle--tracking', {
    route: app.request.attributes.get('_route'),
    routeArgs: app.request.attributes.get('_route_params'),
    type: 'article',
    dimensions: {
        content_category: article.category.slug,
        plan: current_plan_code
    }
}) }}>
```

The tracking endpoint validates direct JavaScript requests independently; rendering through Twig is not a security boundary.

## Accepted shape and limits

`dimensions` must be a JSON object:

- at most 20 entries;
- keys start with an ASCII letter and contain at most 64 letters, digits, dots, underscores or hyphens;
- values are strings, integers, finite floats or booleans;
- strings contain at most 255 characters and cannot be blank;
- nested objects, lists and `null` values are rejected;
- the normalized JSON object cannot exceed 4096 bytes;
- keys are sorted before storage for deterministic output.

An omitted or empty object is stored as `null`. Invalid dimensions reject the whole tracking request with HTTP `400`; no partial hit is created.

## Storage and migration

The bundled `PageCallHitTrait` maps `dimensions` as a nullable Doctrine `json` column. Doctrine DBAL provides the portable mapping used by SQLite, PostgreSQL and MySQL.

After upgrading to the release that introduces dimensions, generate and review a migration in every consuming application:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

Existing rows remain valid with `NULL`. Applications using a custom hit entity with the trait receive the same mapping. Applications implementing the marker interface without the trait can add compatible `getDimensions()` / `setDimensions()` methods or replace `TrackingEntityAccessor`; otherwise the optional values are ignored to preserve the historical custom-entity contract.

## Privacy guidance

Do not place names, email addresses, account identifiers, free-form user input, IP addresses or fingerprinting attributes in dimensions. Prefer a small controlled vocabulary that answers a defined reporting question. If a dimension is only useful temporarily, align its lifecycle with the hit-retention policy rather than copying it to a longer-lived store.
