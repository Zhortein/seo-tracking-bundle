# Historical grouping-key backfill

Version 1.3 introduced deterministic grouping keys for new page calls. It deliberately kept `grouping_key` nullable so an upgrade could not delete or silently merge historical rows that PostgreSQL had allowed because their UTM columns contained `NULL`.

The backfill command added in 1.4 is read-only by default:

```bash
php bin/console zhortein:seo-tracking:backfill-grouping-keys
```

It reports:

- historical rows with a null key;
- rows that can receive a key without conflict;
- rows belonging to an already represented functional group;
- invalid rows that cannot be grouped safely.

The functional key uses the canonical URL when present, otherwise the stored URL, the five UTM values and the bot flag. It therefore follows the same contract as new 1.3 traffic.

## Safe operating procedure

1. Back up `seo_page_call` and `seo_page_call_hit`.
2. Run the command without options and archive its output.
3. Stop writes to the tracking endpoint for the apply window. This avoids a new group being created between inspection and update.
4. Backfill non-conflicting rows in bounded batches:

   ```bash
   php bin/console zhortein:seo-tracking:backfill-grouping-keys \
       --apply \
       --batch-size=250
   ```

5. Review the remaining conflict count.
6. If the application uses the bundle's default `PageCall` entity and the duplicates really represent the same functional group, explicitly consolidate them:

   ```bash
   php bin/console zhortein:seo-tracking:backfill-grouping-keys \
       --apply \
       --merge-duplicates \
       --batch-size=100
   ```

7. Run the dry-run again. A fully processed default installation reports zero historical rows.
8. Resume tracking and verify a normal and Turbo track/exit cycle.

Both apply modes are resumable and safe to rerun. Rows already carrying a key are not rewritten. Without `--merge-duplicates`, a conflicting row remains nullable and no hit is moved or deleted.

## What consolidation does

The built-in merger is intentionally limited to the bundle's default `PageCall` entity. It:

- keeps the page call that already owns the functional key;
- reassigns every duplicate hit to that survivor;
- adds the aggregate call counts;
- keeps the earliest first-call time and latest last-call time;
- removes only the now-empty duplicate page-call row.

Hit timestamps, observed URLs, referrers, parent-hit links and other hit data are preserved.

Custom page-call entities may contain user, tenant, publication or other application-owned fields whose retention policy cannot be guessed. For that reason, `--merge-duplicates` refuses a custom page-call class unless the application replaces:

```php
Zhortein\SeoTrackingBundle\DataLifecycle\Grouping\DuplicatePageCallMergerInterface
```

The custom merger must explicitly decide which entity survives, preserve or reconcile its extra fields, reassign every configured hit, and remove only the duplicate.

## Making the column non-null

The bundle keeps the mapped column nullable for upgrade compatibility. An application may generate a migration making its concrete column non-null only after:

- a final dry-run reports no historical rows;
- a database query confirms that no null key remains;
- the custom-entity policy has been exercised on a production copy;
- the application has a tested rollback.

This is an application migration, not an automatic bundle action.

## Rollback

Backfilled keys can be set back to `NULL` from the pre-operation backup. A consolidation physically removes duplicate page-call rows, so restoring their original identifiers and associations requires restoring both tracking tables from that backup. Do not use `--merge-duplicates` without a restorable snapshot.
