# Upgrade to 1.4

Version 1.4 adds operational data-lifecycle commands and generic consent hooks. It does not change the Doctrine mapping introduced in 1.3.

## Compatibility

The supported runtime remains PHP 8.3 or later, Symfony and AssetMapper 7.3, 7.4 or 8.x, Doctrine DBAL 3 or 4, Doctrine ORM 3.3 or later or 4.x, and Stimulus 3.

## Update

1. Back up the application and tracking tables.
2. Update the package:

   ```bash
   composer require zhortein/seo-tracking-bundle:^1.4
   ```

3. Clear the Symfony cache using the application's normal deployment process.
4. Publish the updated Stimulus controller:

   ```bash
   php bin/console asset-map:compile
   ```

5. Exercise one classic and one Turbo track/exit cycle in a staging environment.

No Doctrine migration is required when upgrading from 1.3. Applications upgrading from 1.2 or earlier must first apply the [1.3 schema migration](upgrade-1.3.md).

## Historical grouping keys

The backfill command is read-only by default:

```bash
php bin/console zhortein:seo-tracking:backfill-grouping-keys
```

Do not pass `--apply` until its output has been reviewed against a database backup. Duplicate consolidation is a separate explicit option and refuses custom page-call entities unless the application supplies its own merger policy. Follow the complete [backfill procedure](grouping-backfill.md).

The bundle continues to map `grouping_key` as nullable. Making the application column non-null remains an optional application migration after a final inventory reports no historical null key.

## Retention

Retention remains disabled because `retention.days` defaults to `null`. Merely installing 1.4 never schedules a task and never deletes data.

To adopt a policy, configure it, run `zhortein:seo-tracking:purge` without `--apply`, archive the preview, then test the destructive run against a production copy. See the [retention and rollback guide](retention.md).

## Consent

The default `AllowAllTrackingConsentChecker` preserves the 1.3 behavior: tracking starts immediately.

Applications that require consent gating must replace `TrackingConsentCheckerInterface`, persist their consent decision before dispatching the configured browser grant event, and verify that a direct tracking request receives HTTP 403 when denied. The exit endpoint deliberately remains available so an already-open hit can be closed after revocation.

Read the complete [consent integration guide](consent.md). This generic mechanism does not determine the application's legal basis, consent categories, erasure policy or consent-management platform.

## Rollback

Downgrading the code to 1.3 requires no schema rollback.

- A dry-run does not need rollback.
- Applied grouping keys can be restored to `NULL` from a backup. Duplicate consolidation deletes redundant page-call rows and therefore requires restoring both tracking tables to recover their original identifiers and associations.
- Retention permanently deletes selected hits and requires restoring the tracking tables to recover them.
- Remove any 1.4 consent event wiring before deploying the 1.3 Stimulus controller.

Stop scheduled purge commands before downgrading.
