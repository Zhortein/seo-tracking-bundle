# Roadmap for SeoTrackingBundle

This file lists improvements deliberately left outside the 1.5 release. Items are exploratory unless they are attached to a GitHub milestone.

The bundle must remain generic: application-specific behavior belongs in configuration, replaceable services, events or overrideable templates.

---

## Tracking

- Visitor-flow reports based on `parentHit`, with clear expiry and privacy semantics. No “unique visitor” metric should be introduced without a reliable identity model and documented consent implications.
- Optional structured metadata for application-defined dimensions, with portable Doctrine mapping considered before platform-specific JSON features.

## Statistics and presentation

- Cache and pagination policies for large datasets.
- Optional pre-aggregated storage for high-volume installations.
- Additional presentation themes and lightweight charts without making a frontend framework mandatory.
- Export adapters for CSV or external analytics systems.

## Compatibility

- Expand database CI when a platform can be supported continuously.
- Review Symfony 7.3 support separately once its ecosystem constraints no longer permit a secure dependency set.

## Delivered in 1.5

- Optional, independently configurable Symfony RateLimiter policies for hit creation and closure.
- Hashed default client-IP limiter keys with a replaceable key resolver and complete policy contract.
- Typed, replaceable bot classification with explainable default categories and legacy detector compatibility.
- Separate invalid-event reporting with stable reasons, bounded safe metadata and a no-op default.

## Delivered in 1.4

- Dry-run-first historical grouping consolidation and `grouping_key` backfill, with an explicit custom-entity merge policy.
- Opt-in hit retention with preview, bounded purge batches and aggregate repair.
- Replaceable server-side consent decisions and generic frontend grant/revoke events without coupling to a consent manager.

## Delivered in 1.3

- Typed statistics provider, DTOs and filters.
- Overrideable Twig rendering with an optional Bootstrap 5 theme.
- Input validation and length limits.
- IPv4 and IPv6 anonymization.
- Configurable entities, factories and endpoint URLs.
- Reliable classic, Turbo and no-beacon tracking lifecycles.
- Cross-platform functional grouping key.

Last reviewed: 2026-07-26.
