# 🧾 CHANGELOG

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

### Migration

- Default entities require a Doctrine migration. See [`docs/upgrade-1.3.md`](docs/upgrade-1.3.md).

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
- GDPR-friendly: no cookies, no personal data.
- Profiler integration to debug UTM parameters.
- Async tracking via `fetch()` and exit detection.
