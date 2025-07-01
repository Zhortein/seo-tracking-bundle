# 🧾 CHANGELOG

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
