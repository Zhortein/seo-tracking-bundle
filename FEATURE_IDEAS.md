# Roadmap for SeoTrackingBundle

This file lists improvements deliberately left outside the 1.7 release. Items are exploratory unless they are attached to a GitHub milestone.

The bundle must remain generic: application-specific behavior belongs in configuration, replaceable services, events or overrideable templates.

---

## Identity and privacy

- No “unique visitor” metric should be introduced without a reliable identity model, explicit consent implications and defined deletion semantics.
- Any future authenticated or cross-device journey model must remain separate from the heuristic `parentHit` fragments.

## Statistics and presentation

- Optional pre-aggregated storage for high-volume installations.
- Database-native cursor implementations for installations where deep offset pagination is insufficient.
- Application-owned adapters for non-CSV external analytics systems.

## Compatibility

- Expand database CI when a platform can be supported continuously.
- Review Symfony 7.3 support separately once its ecosystem constraints no longer permit a secure dependency set.

## Delivered in 1.7

- Bounded filtered observation pagination with a replaceable browser contract.
- Opt-in, fail-open PSR-6 caching for repeated aggregate statistics reports.
- Lazy, formula-safe UTF-8 CSV exports over the same exact typed filters.
- A dependency-free semantic HTML5 theme alongside the backward-compatible Bootstrap 5 default.
- English and French presentation catalogs, accessible proportional visuals and exact tabular equivalents.

## Delivered in 1.6

- Typed, bounded journey fragments and transition reports based on `parentHit`, with explicit retention, consent and filter-boundary semantics.
- Portable, bounded application-defined scalar dimensions stored on individual hits.
- Exact dimension filters, typed rankings and Bootstrap rendering without platform-specific JSON queries.
- Backward-compatible statistics for legacy custom hit entities without a dimensions field.

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
