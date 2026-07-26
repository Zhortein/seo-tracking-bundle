# Upgrade to 1.3

Version 1.3 changes the default tracking schema. Applications using custom entities only need the fields they choose to expose, but classes using the bundle traits inherit the same mapping changes.

## Schema changes

`seo_page_call`:

- widens `url` to 2048 characters;
- adds nullable `canonical_url` (the exact generated column name depends on the naming strategy);
- adds nullable `grouping_key` with a unique constraint;
- removes the composite unique constraint based on URL, nullable UTM fields and `bot`.

`seo_page_call_hit`:

- adds a nullable 2048-character `url` containing the URL actually observed for the hit;
- makes `referrer` nullable and widens it to 2048 characters.

The grouping key remains nullable for migrated rows so the migration does not fail when historical PostgreSQL rows contain duplicate groups created by `NULL` semantics. Every hit created by 1.3 writes a 64-character key, so new traffic receives functional uniqueness on every supported database platform. Historical rows remain readable and are not deleted or merged automatically.

## Safe rollout

1. Back up the tracking tables.
2. Stop writes to the two tracking endpoints or deploy the migration and application in one maintenance window.
3. Install the new bundle version:

   ```bash
   composer require zhortein/seo-tracking-bundle:^1.3
   ```

4. Generate the migration:

   ```bash
   php bin/console doctrine:migrations:diff
   ```

5. Review that the generated migration performs the changes above. In particular, `grouping_key` must initially be nullable.
6. Test both directions against a copy of production data:

   ```bash
   php bin/console doctrine:migrations:migrate --dry-run
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:migrations:migrate prev
   php bin/console doctrine:migrations:migrate
   ```

7. Compile the application's assets when production uses AssetMapper:

   ```bash
   php bin/console asset-map:compile
   ```

8. Deploy the application and verify a track/exit cycle, including a Turbo navigation when Turbo is enabled.

Do not make `grouping_key` non-null until historical data has been explicitly consolidated and backfilled. That optional consolidation is deliberately outside the automatic migration because choosing which duplicate aggregate to retain is application data policy.

## Local overrides that may no longer be needed

Applications can remove local copies or decorations that existed only to work around the following pre-1.3 limitations:

- the invalid distributed Stimulus controller;
- hard-coded tracking and exit endpoint URLs;
- duplicate listeners or unclosed hits during Turbo navigation and Stimulus reconnection;
- direct construction of the bundle's default `PageCall` and `PageCallHit` classes;
- IPv4-only IP anonymization;
- missing canonical-URL grouping.

Before deleting an override, compare it with the application's current requirements. Keep application-specific consent, retention, authorization, enrichment or presentation logic. Custom entities remain supported through configuration and factories, and custom statistics presentation remains supported through Twig templates and themes.

## Rollback

The added columns and grouping-key constraint can be dropped. Restoring the old composite constraint is possible only after checking for historical duplicates. Reducing URL and referrer lengths back to their former limits can truncate data, and restoring a non-null referrer requires replacing or removing null rows first. Keep the backup until the release has been validated.
