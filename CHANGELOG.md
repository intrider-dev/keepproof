# Changelog

All notable changes to KeepProof will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and releases follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html). While the project is below `1.0.0`, an incompatible change increments the minor version and is called out explicitly.

## [Unreleased]

### Added

- Laravel 13 modular-monolith API with stateful-cookie authentication, verified accounts, personal households, owner/member authorization, purchases, private receipts, notifications, exports, and privacy operations.
- Independent strict PHP Deadline Engine with explainable return/warranty rules, manual overrides, timezone-safe calendar dates, and table/property-style boundary tests.
- Asynchronous receipt state machine with database-authoritative dispatch leases, bounded retries, local Tesseract and deterministic extraction, human review, and safe optional-LLM contracts.
- Vue 3 and TypeScript SPA covering landing, authentication, dashboard, purchase, upload/review, household, notification, export, and deletion flows.
- PostgreSQL, Redis, SeaweedFS, Mailpit, worker, scheduler, Nginx, backend, and frontend development stack with production-oriented image targets.
- OpenAPI 3.1 contract, generated frontend client, Problem Details errors, correlation IDs, health endpoints, synthetic demo data, and local Swagger UI.
- In-app/email reminders, ICS export, claim packages, account ZIP export, private-original deletion, audit records, and delivery-uncertainty handling.
- Backend, package, frontend, accessibility, contract, security, failure, and connected Playwright tests, plus a reproducible local read/upload load probe.
- CI, release-candidate artifact/SBOM workflows, Dependabot, contribution templates, security guidance, ADRs, architecture, case study, recruiter tour, and maintainer notes.

### Changed

- External AI remains disabled by default and requires per-document consent; no machine-extracted deadline activates before human confirmation.
- Redis is treated as a recoverable wake-up transport while PostgreSQL retains durable processing intent.
- Reminder email failures after an ambiguous SMTP boundary enter `delivery_unknown` instead of being retried automatically.

### Security

- Receipt images are content-sniffed and re-encoded; object keys are application-generated and objects remain private behind short-lived signed URLs.
- Household-scoped route binding and policies return non-disclosing failures for foreign tenant identifiers.
- Signed receipt-link responses are marked `private, no-store`; sensitive receipt content, OCR text, and signed URLs are excluded from structured logs.
- Export and account deletion require verified email plus recent-password confirmation and create audit records.

### Fixed

- Clean frontend dependency volumes are writable by the non-root runtime user.
- Compose environment values remain visible to Laravel's local HTTP server, and Nginx resolves recreated containers dynamically.
- Internal HTTP Playwright runs use a secure UUIDv4 fallback when `crypto.randomUUID()` is unavailable.
- Signed email-verification navigation restores the browser session through the web middleware boundary while retaining the versioned URL.
- Composer test scripts force the testing environment so stateful local Compose settings cannot accidentally enable CSRF in feature tests.
- Clean Deadline Engine dependency volumes are installed and owned by the non-root package runtime, making first-run package checks reproducible.
- Local OCR accepts punctuation produced by real Tesseract labels, normalizes receipt identifiers consistently, and probes Poppler with its supported version flag so both PNG and PDF contracts execute in-container.
- Production Nginx runs as a non-root user with writable PID and temporary paths while preserving structured access logs that omit query strings.

No version has been published yet. Release links will be added after the repository has a public GitHub origin and its first tag.
