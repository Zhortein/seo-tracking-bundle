# 🧾 CHANGELOG

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Fixed

- Rendered empty tracking dimensions as a JSON object so Stimulus accepts the
  declared `Object` value and page tracking starts without custom dimensions.

---

## [1.7.1] – 2026-07-26

### Fixed

- Corrected the configuration tree root used by `config:dump-reference` to
  match the public `zhortein_seo_tracking` extension alias.

### Documentation

- Reworked the README as a concise project and compatibility landing page.
- Added a complete documentation index, end-to-end quick start, exhaustive
  configuration reference and application cookbook.
- Documented the data model, custom-entity contract and historical Easylyse
  forwarding behavior, including its privacy and operational boundaries.
- Added CI coverage for required guides, local Markdown links and public
  configuration keys.

---

## [1.7.0] – 2026-07-26

### Added

- A bounded, replaceable observation browser for filtered administrative listings, with explicit offset and page-size limits.
- Disabled-by-default PSR-6 statistics report caching with deterministic, type-sensitive keys and fail-open backend handling.
- A lazy UTF-8 CSV statistics exporter with a stable schema, configurable dialect and spreadsheet-formula protection.
- A framework-neutral semantic HTML5 statistics theme without CSS, JavaScript or remote assets.
- English and French translation catalogs for the bundled statistics presentation.
- Accessible proportional visuals with exact textual counts and complete daily trend tables.
- Documentation and functional coverage for pagination, cache policy, exports, authorization, presentation overrides and rollback.

### Changed

- The default Bootstrap 5 statistics theme now uses translated labels and supplements rankings and trends with bounded proportional visuals.
- Aggregate reports can be cached only when applications explicitly configure both a pool and a positive TTL.
- Pagination, exports and aggregate reports share the same typed, exact `StatisticsFilter` semantics.
- Bundled presentation keeps stable `summary`, `trend`, `rankings` and `dimensions` Twig blocks across both themes.

### Migration

- No Doctrine schema migration is required. PSR cache interfaces are installed automatically, while report caching remains disabled unless configured. See [`docs/upgrade-1.7.md`](docs/upgrade-1.7.md).

---

## [1.6.0] – 2026-07-26

### Added

- A typed, replaceable journey-reporting API based on persisted `parentHit` links.
- Bounded path fragments, transition rankings and summaries with explicit filter-boundary, cycle and depth-truncation metadata.
- Optional application-defined hit dimensions with portable nullable Doctrine JSON storage.
- Shared dimension normalization with bounded keys, scalar values, entry count and encoded size.
- Exact, type-sensitive dimension filters and typed per-dimension rankings in the statistics API.
- A dedicated custom-dimensions block in the bundled Bootstrap 5 statistics theme.
- Documentation and functional coverage for journey semantics, dimension privacy, custom entities and migration.

### Changed

- The Stimulus controller and Twig helper can send explicit application-defined dimensions with every new hit.
- The default statistics source hydrates and filters dimensions without database-specific JSON operators.
- Legacy custom hit entities without a dimensions field continue to use the default statistics source with empty dimension rankings.
- Journey reports deliberately describe graph fragments rather than visitors, devices or stable sessions.

### Migration

- Default and trait-based custom hit entities require a nullable Doctrine JSON `dimensions` column. Existing rows remain valid with `NULL`; no data backfill is required. See [`docs/upgrade-1.6.md`](docs/upgrade-1.6.md).

---

## [1.5.0] – 2026-07-26

### Added

- Disabled-by-default Symfony RateLimiter integration with independent hit-creation and hit-closure policies.
- A replaceable, privacy-conscious rate-limit key resolver that hashes Symfony's resolved client IP by default.
- Typed, explainable bot classifications with conservative search, social-preview, monitoring and crawler categories.
- A replaceable bot classifier while preserving existing `BotDetectorInterface` integrations through an adapter.
- Bot classification metadata on `PageCallTrackedEvent` without adding persisted fields.
- A separate no-op-by-default invalid-event reporter with stable reasons and bounded safe metadata.
- Dedicated documentation and functional coverage for rate limiting, bot classification and invalid-event observability.

### Changed

- Rate limiting is evaluated before consent and payload parsing when it is enabled.
- Valid JSON whose root is not an object is distinguished from an object containing invalid fields.
- The default bot detector recognizes additional common crawler families while remaining heuristic.
- Rejected requests can be observed without creating hits or affecting SEO statistics.
- CI aligns and tests Symfony Clock and RateLimiter across the supported Symfony matrix.

### Migration

- No Doctrine schema migration is required. See [`docs/upgrade-1.5.md`](docs/upgrade-1.5.md) for defaults, optional dependencies and rollout checks.

---

## [1.4.0] – 2026-07-26

### Added

- A dry-run-first command for historical grouping-key inventory and backfill.
- Explicit duplicate consolidation for the default entities, with a replaceable merger contract for custom entities.
- An opt-in retention policy and dry-run-first purge command with bounded batches and aggregate repair.
- A replaceable consent checker plus configurable frontend grant/revoke events, with immediate tracking preserved by default.

### Changed

- The Stimulus controller now delays collection while consent is pending, closes the current hit once on revocation and starts a fresh flow after a later grant.
- The tracking endpoint enforces the configured consent checker while the exit endpoint remains available to close an existing hit.
- Symfony Console is now an explicit runtime dependency for the bundle commands.

### Migration

- No Doctrine schema migration is required. See [`docs/upgrade-1.4.md`](docs/upgrade-1.4.md) for the opt-in operational steps.

---

## [1.3.0] – 2026-07-26

### Added

- Configurable factories for `PageCallInterface` and `PageCallHitInterface`.
- Configurable tracking and exit URLs, with route-generated defaults.
- IPv4 and IPv6 prefix anonymization through a replaceable service.
- Canonical URL grouping while preserving the observed URL on each hit.
- A deterministic non-null grouping key for newly tracked calls.
- A typed statistics API with period, timezone, robot and page-type filters.
- Reliable summaries, duration samples, top pages, UTM rankings, route/type/language breakdowns and daily evolution.
- An optional, overrideable Bootstrap 5 Twig statistics theme.

### Fixed

- Configured entity classes are now used by repositories, creation, exit handling and events.
- Malformed JSON, missing optional fields and absent user agents return controlled responses.
- Hit closure is idempotent.
- Stimulus/Turbo lifecycle handling closes hits once, avoids duplicate listeners and starts a new hit when a hidden page becomes visible again.
- Exit delivery falls back to `fetch(..., {keepalive: true})` when `sendBeacon()` is missing or refuses the payload.
- The distributed Stimulus controller is valid JavaScript.
- The ineffective nullable UTM composite uniqueness constraint is replaced by a grouping-key constraint.
- The PHPStan baseline was removed after fixing the audited errors.
- CI now verifies SQLite, PostgreSQL 16 and MySQL 8.4 in addition to the PHP, Symfony and Node matrices.

### Migration

- Default entities require a Doctrine migration. See [`docs/upgrade-1.3.md`](docs/upgrade-1.3.md).

---

## [1.2.11] – 2026-04-09

### Fixed

- Switched bundle service loading from the removed XML file to the YAML service definition.

---

## [1.2.10] – 2026-04-09

### Changed

- Added DoctrineBundle 3.2 compatibility.

---

## [1.2.9] – 2026-04-09

### Changed

- Added Symfony 8 and Doctrine ORM 4 compatibility.

---

## [1.2.8] – 2026-02-24

### Added

- Added the `idSite` page-call field.

---

## [1.2.7] – 2026-02-18

### Fixed

- Normalized empty Twig route arguments.

---

## [1.2.6] – 2026-02-18

### Fixed

- Corrected the Stimulus route-argument value declaration and its Twig default.

---

## [1.2.5] – 2026-02-18

### Changed

- Updated package compatibility metadata.

---

## [1.2.4] – 2026-02-18

### Fixed

- Allowed null referrers and updated hit duration when an exit date is set.
- Corrected the default Easylyse endpoints.

---

## [1.2.3] – 2025-08-11

### Added

- Added optional Easylyse page-call and exit forwarding.

---

## [1.2.2] – 2025-07-06

### ✨ Added
- Extensible entities for advanced usage

---

## [1.2.1] – 2025-07-01

### ✨ Added
- New Twig helper `seo_tracking(type)` to simplify usage:
  Instead of writing Stimulus manually, you can now do:
  ```twig
  <div {{ seo_tracking('home') }}></div>
  ```

---

## [1.2.0] – 2025-07-01

> ⚠️ **This version requires a Doctrine migration** (new fields in `PageCall` and `PageCallHit`)

### ✨ Added
- Field `bot` in `PageCall`: detects and flags bot-related traffic.
- Fields in `PageCallHit`:
    - `bot`: flags bot visits based on User-Agent and heuristics.
    - `pageTitle`: stores the document title (provided by JS).
    - `delaySincePreviousHit`: calculates delay (in seconds) since `parentHit` if available.
    - `pageType`: stores an optional value representing the semantic type of the page (e.g. `home`, `form`, `contact`, etc.).
- Fallback handling for Stimulus tracking controller using HTML `data-*` attributes and `Values API`.

### 🧰 Developer Experience
- Updated README.md with new examples, shields, and clearer usage instructions.
- Improved exception handling in Stimulus controller.
- Added JS fallback in case of execution errors to ensure graceful degradation.

---

## [1.1.0] – 2025-07-01

> ⚠️ **This version needs to create a Doctrine migration**

### ✨ Added
- **Support for parentHit** in `PageCallHit`: you can now associate a hit with a parent hit to track navigation flows.
- Error handling and validation for `/track` and `/exit` endpoints (invalid JSON, missing URL or hitId, etc.).
- File `FEATURE_IDEAS.md` added to track suggestions and ideas.
- File `CONTRIBUTING.md` added for contributors.
- File `CHANGELOG.md` added.

### 📦 Changed
- Minor internal optimizations in the controller (reuse of `$em` instead of injecting repositories).

---

## [1.0.0] – 2025-06-28

> ⚠️ **This version needs to create a Doctrine migration**

### 🎉 Initial release
- Symfony bundle with Stimulus-based page tracking.
- Tracks: current URL, route, UTM params, language, screen size, entry/exit time.
- Cookie-free asynchronous page tracking with IP anonymization.
- Profiler integration to debug UTM parameters.
- Async tracking via `fetch()` and exit detection.
