# Upgrade to 1.6

Version 1.6 adds read-only journey reports over existing parent-hit links and optional application-defined dimensions stored on individual hits. Dimensions add one nullable Doctrine JSON field to the default and trait-based hit mappings.

## Compatibility

The supported runtime remains PHP 8.3 or later, Symfony and AssetMapper 7.3, 7.4 or 8.x, Doctrine DBAL 3 or 4, Doctrine ORM 3.3 or later or 4.x, and Stimulus 3.

Journey reports reuse the `parentHit` mapping already present in earlier releases. They require no schema or data migration.

## Prepare the schema migration

Applications using the bundled `PageCallHit` entity or `PageCallHitTrait` must add a nullable `dimensions` column using Doctrine's portable `json` type.

1. Back up the application and tracking tables according to the normal deployment procedure.
2. Update the dependency in a development or staging checkout:

   ```bash
   composer require zhortein/seo-tracking-bundle:^1.6
   ```

3. Generate a migration using the application's normal Doctrine workflow:

   ```bash
   php bin/console make:migration
   ```

4. Review the generated SQL. For this bundle it should add one nullable JSON-compatible `dimensions` column to the configured hit table, without an index, constraint or data rewrite.
5. Test the migration and its rollback against a production-like backup.

Existing rows remain valid with `NULL`; no backfill is required or supplied.

## Deployment order

The safest rolling deployment is migration-first because version 1.5 ignores an extra nullable column:

1. deploy the reviewed application migration;
2. run `php bin/console doctrine:migrations:migrate`;
3. deploy the application code using bundle 1.6;
4. clear the Symfony cache through the application's normal process;
5. recompile the asset map:

   ```bash
   php bin/console asset-map:compile
   ```

6. exercise dimension collection, statistics and a short known journey in staging.

Avoid serving 1.6 with the trait mapping before its column exists: Doctrine inserts and the default statistics query expect the mapped field.

## Custom entities

- A custom hit entity using `PageCallHitTrait` receives the new mapping and requires the same nullable column migration.
- A custom hit entity with compatible `getDimensions()` / `setDimensions()` methods owns its mapping and migration.
- A custom hit entity without `setDimensions()` continues to ignore submitted dimensions.
- The default statistics source detects an absent dimensions field and continues to report historical metrics with empty dimension rankings. Exact dimension filters match none of those rows.
- A custom statistics or journey data source remains responsible for applying the supplied filters and returning the documented DTO observations.

## Adopt dimensions deliberately

Dimensions are never inferred. Add only controlled business values through the third `seo_tracking()` argument or direct Stimulus configuration. The endpoint independently rejects:

- more than 20 entries;
- invalid or oversized keys;
- blank or oversized strings;
- nested arrays or objects and `null`;
- non-finite floats;
- a normalized object larger than 4096 bytes.

Do not use names, email addresses, account identifiers, free-form user input, IP addresses or fingerprinting attributes. Review consent, authorization, privacy notice and retention before enabling any dimension.

## Journey semantics

`JourneyProviderInterface` is immediately available after the code update. It reads existing `parentHit` links and does not write or consolidate data.

Treat every result as a bounded graph fragment. Consent revocation, retention, browser-session loss and missing historical parents can all start or cut a fragment. Do not label a path as a visitor, device or stable session.

## Verification

In staging, verify:

1. the migration adds only the expected nullable JSON-compatible column;
2. a hit without dimensions and a hit with controlled scalar dimensions both return HTTP 200;
3. a nested or oversized dimension payload returns HTTP 400 and creates no partial hit;
4. statistics rank the stored values and distinguish the integer `1` from the string `"1"`;
5. an exact dimension filter excludes non-matching and untagged hits;
6. a known `parentHit` chain produces the expected transition and bounded path;
7. custom entities and custom data sources follow the intended optional-field behavior.

## Rollback

Before downgrading application code to 1.5:

- stop templates or JavaScript from sending dimensions;
- remove application calls to the journey API and dimension filters or rankings;
- deploy the previous code and clear its cache.

Keep the nullable `dimensions` column during the immediate rollback. Version 1.5 ignores it, and retaining it preserves collected values and avoids a destructive schema operation. Remove the column only in a later, separately reviewed migration after taking a backup and confirming that no application code or retained data needs it.

Journey reporting is read-only, so it requires no data rollback. Hits collected by 1.6 retain their existing `parentHit` links and other historical fields.
