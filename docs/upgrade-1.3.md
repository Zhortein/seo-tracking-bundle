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
3. Install the new bundle version.
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

7. Deploy the application and verify a track/exit cycle.

Do not make `grouping_key` non-null until historical data has been explicitly consolidated and backfilled. That optional consolidation is deliberately outside the automatic migration because choosing which duplicate aggregate to retain is application data policy.

## Rollback

The added columns and grouping-key constraint can be dropped. Restoring the old composite constraint is possible only after checking for historical duplicates. Reducing URL and referrer lengths back to their former limits can truncate data, and restoring a non-null referrer requires replacing or removing null rows first. Keep the backup until the release has been validated.
