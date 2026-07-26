# Tracking-data retention

Retention is opt-in. Installing or upgrading the bundle never schedules a purge and never deletes tracking data.

Configure a maximum hit age and bounded batch size:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    retention:
        days: 180
        batch_size: 500
        remove_empty_page_calls: false
```

`days: null` is the backward-compatible default and means that no policy cutoff exists. `remove_empty_page_calls` is also false by default: a purge then keeps an emptied page-call aggregate with `nbCalls = 0`, its historical first-call timestamp and no last-call timestamp.

## Preview before applying

The command is read-only unless `--apply` is present:

```bash
php bin/console zhortein:seo-tracking:purge
```

It reports the calculated cutoff, candidate hits, affected page calls and hits without a usable `calledAt` value. Undated hits are never deleted by this policy.

An absolute cutoff can be used for a one-off operation, even when `retention.days` is disabled:

```bash
php bin/console zhortein:seo-tracking:purge \
    --before='2026-01-01T00:00:00+00:00'
```

After reviewing the output and taking a database backup:

```bash
php bin/console zhortein:seo-tracking:purge --apply --no-interaction
```

Override the operational batch size without changing configuration:

```bash
php bin/console zhortein:seo-tracking:purge \
    --apply \
    --batch-size=250 \
    --no-interaction
```

The operation is resumable and idempotent. Each batch unlinks surviving hits whose `parentHit` is being purged, deletes only hits strictly older than the cutoff, then recomputes `nbCalls`, `firstCalledAt` and `lastCalledAt` from the remaining hits. These changes are committed atomically.

To remove page-call aggregates emptied by the current purge, opt in explicitly:

```bash
php bin/console zhortein:seo-tracking:purge \
    --apply \
    --remove-empty-page-calls
```

Existing empty aggregates that have no candidate hit are not swept incidentally.

## Scheduling

First exercise the command against a copy of production data and run a manual dry-run. A conventional daily cron entry is then sufficient:

```cron
15 3 * * * cd /path/to/application && php bin/console zhortein:seo-tracking:purge --apply --no-interaction
```

Applications using Symfony Scheduler can invoke the same command or inject `HitRetentionPurgerInterface` from their own scheduled message. The bundle deliberately does not register a schedule: cadence, monitoring, maintenance windows and legal retention policy belong to the consuming application.

## Custom entities

The purger uses the configured page-call and hit classes. The default implementation requires:

- one scalar identifier on each entity;
- a hit `calledAt` field and `pageCall` association;
- page-call `nbCalls`, `firstCalledAt` and `lastCalledAt` fields.

Classes using the supplied traits meet this contract. Applications that deliberately use another model can replace `HitRetentionPurgerInterface` or run their own retention workflow.

## Operational and rollback notes

- Back up both tracking tables before the first apply run.
- Run only one purge process at a time.
- Monitor the command exit status and archived output.
- Check the effect of foreign keys on a production copy. The command explicitly clears affected parent links, and the default relation also uses `ON DELETE SET NULL`.
- A purge is destructive. Restoring removed hits, their original identifiers and parent links requires restoring the tracking tables from backup.
