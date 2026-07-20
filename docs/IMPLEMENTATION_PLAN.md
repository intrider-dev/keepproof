# KeepProof Implementation Plan

Status: active delivery plan
Last updated: 2026-07-20

## Outcome

KeepProof is a privacy-first receipt, return-window, and warranty tracker for individuals and families. The first release must complete one honest end-to-end path: a verified user stores a synthetic receipt, reviews locally extracted data, confirms the purchase, receives calculated deadlines and reminders, and can export or delete the resulting data.

This plan is an execution contract, not a claim that a phase already passes. A phase is complete only after its acceptance commands have been run successfully and the result has been recorded in the repository.

## Product assumptions

- The primary tenant is a small household. Every registration creates a personal household and an owner membership.
- Household members share visibility of household purchases. Per-purchase access control is outside the MVP.
- Return and warranty terms come from the receipt or the user. KeepProof does not maintain a jurisdictional law database.
- A purchase date and deadline are calendar dates in a recorded IANA timezone. Scheduled delivery instants are stored in UTC.
- Finalized purchases contain at least one item. Draft purchases may be incomplete while a receipt is processed.
- Money is stored as integer minor units with an ISO 4217 currency code. Floating-point money is prohibited.
- English is the default UI and documentation language. UI strings are structured for later localization.
- Local OCR, deterministic fixtures, and manual entry must cover the complete workflow without a paid API.
- External AI is optional, disabled by default, and requires explicit consent for each document.
- Local development uses SeaweedFS 4.39 through its S3-compatible API. Production storage remains portable to another private S3-compatible provider.
- Published performance figures must come from a reproducible local run and must state hardware, dataset size, concurrency, and limitations.

## Non-goals for the first release

- Legal advice, automatic interpretation of consumer law, or a guarantee that a claim will succeed.
- Microservices, Kafka, event sourcing, CQRS, Kubernetes, GraphQL, or a generic rules engine.
- Billing, social features, mobile applications, multi-region active-active deployment, or enterprise identity federation.
- Automatic currency conversion or financial reporting.
- Exactly-once email delivery, which SMTP cannot guarantee across an ambiguous process failure.
- Handwriting-grade OCR or support for arbitrary document formats.
- A Python or FastAPI service when a bounded local process adapter is sufficient.
- Symfony and Yii examples before every MVP quality gate passes.

## Pinned compatibility baseline

The initial implementation is pinned to the following mutually compatible baseline. Dependency changes require a reviewed pull request, successful quality gates, and an ADR amendment when they alter an architectural decision.

| Component | Version | Role |
| --- | ---: | --- |
| PHP | 8.5.8 | Backend runtime and deadline package |
| Laravel | 13.20 | API, authentication, queues, scheduling, and mail |
| PostgreSQL | 18.4 | Primary transactional database |
| Redis | 8.2.7 | Queues, non-authoritative locks, and cache |
| Node.js | 24.18 | Frontend toolchain runtime |
| Vue | 3.5.40 | SPA framework |
| TypeScript | 6 | Strict frontend type system |
| Vite | 8.1.5 | Frontend development and production build |
| SeaweedFS | 4.39 | Local private S3-compatible object storage |

Exact image digests and JavaScript lockfile resolutions are recorded in infrastructure and lock files. Versions are not advanced merely to obtain a larger version number.

## Repository and dependency shape

- apps/backend is the Laravel API and scheduler/worker application.
- apps/frontend is the Vue and TypeScript SPA.
- packages/deadline-engine is a strict, framework-agnostic Composer package.
- tests/fixtures contains synthetic receipts and expected extraction results.
- infra contains local and production-oriented container configuration.
- docs contains architecture, security, operational, and portfolio documentation.

The Laravel application is divided by capability: Accounts, Households, Purchases, Documents, Reminders, Exports, and Audit. HTTP controllers and console commands delegate to verb-named actions. Actions may use Eloquent directly. Repository interfaces and generic service classes are not introduced.

Dependency direction is deliberately small:

1. HTTP and console adapters call application actions.
2. Accounts and Households establish identity and tenant membership.
3. Purchases uses the independent deadline engine.
4. Documents may apply confirmed extraction data through a Purchase action.
5. Reminders consumes active purchase deadlines.
6. Exports and privacy operations orchestrate authorized reads and deletion across modules.
7. Only external OCR and receipt extraction providers receive application-owned interfaces.

Critical cross-module writes are direct and transactional. Events are reserved for after-commit wake-ups and non-critical side effects.

## Delivery rules

- Each phase ends with tests appropriate to the risk introduced in that phase.
- Database state is authoritative. Redis jobs are wake-up signals and can be reconstructed by a reconciliation command.
- No database transaction remains open during S3, OCR, SMTP, or external HTTP work.
- Every tenant resource carries a household identifier, is loaded through a tenant-scoped query, and is checked by a policy.
- All public errors use Problem Details and include a correlation identifier without sensitive input.
- Extracted fields are provisional until a person explicitly confirms them.
- Documentation and screenshots describe only commands and behavior that have been verified.
- Stretch work is opened as an issue rather than merged in a partial state.

## Phase 0 — Foundation and executable environment

Deliverables:

- Root task runner with setup, lifecycle, quality, test, seed, demo, log, and reset commands.
- Multi-stage backend and frontend images, non-root runtime users where practical, health checks, and graceful worker shutdown.
- Compose services for Nginx, backend, frontend, PostgreSQL, Redis, SeaweedFS, local mail, queue worker, scheduler, and local OCR dependencies.
- Safe environment examples, structured JSON logging, correlation IDs, and live/ready health endpoints.
- Small migrations, deterministic factories, and an empty-database migration path.

Acceptance gate:

- A clean checkout can build all images and start without an external paid account.
- PostgreSQL, Redis, SeaweedFS, mail, worker, scheduler, backend, frontend, and proxy health can be observed.
- Migrations run successfully against an empty PostgreSQL volume.
- No secret, real email address, or personal receipt is present in tracked files.

## Phase 1 — Deadline Engine

Deliverables:

- Independent Composer package with strict types, PSR-4 loading, immutable value objects, and no Laravel dependency.
- Return and warranty periods, inclusive and exclusive rules, manual overrides, timezone-aware inputs, and explainable results.
- Explicit behavior for leap years, end-of-month arithmetic, and daylight-saving boundaries.
- Table-driven boundary tests and generative/property-style invariants.
- Package README with executable examples.

Acceptance gate:

- Composer validation, formatting, static analysis, and package tests pass independently.
- Tests demonstrate leap-day, month-end, timezone transition, inclusive/exclusive, and override behavior.
- The package can be required without booting Laravel.

## Phase 2 — Identity, tenancy, and API contract

Deliverables:

- Sanctum stateful-cookie registration, login, logout, email verification, password reset, and recent-password confirmation.
- Atomic creation of user, personal household, and owner membership.
- Owner/member policies, minimal email invitation flow, and atomic ownership transfer.
- Versioned /api/v1 routes, OpenAPI 3.1 contract, generated TypeScript client, and Problem Details errors.
- Database sessions, exact CORS allowlist, CSRF protection, secure cookie settings, and login throttling.

Acceptance gate:

- Authentication and email-verification feature tests pass.
- Tests prove a user cannot read or mutate another household by guessing a ULID.
- Reusing a nested resource identifier under another household returns a non-disclosing error.
- OpenAPI validation and generated-client type checking pass.

## Phase 3 — Purchases and private documents

Deliverables:

- Draft and active purchases, items, integer money, retailer terms, manual overrides, filters, pagination, and search indexes.
- Secure JPEG, PNG, and PDF uploads with content-derived MIME detection, size/page/pixel limits, safe names, and image metadata removal.
- Private SeaweedFS objects with opaque keys and short-lived signed download URLs.
- Controlled document state machine and immutable transition journal.
- Upload idempotency and compensating cleanup for object/database boundary failures.

Acceptance gate:

- Purchase CRUD, validation, policy, search, and no-obvious-N+1 tests pass.
- Upload tests reject extension spoofing, disallowed MIME, oversized input, and cross-tenant access.
- Stored image fixtures contain no EXIF metadata.
- Private objects cannot be listed or fetched anonymously, and quarantined objects cannot receive a URL.

## Phase 4 — Asynchronous extraction

Deliverables:

- Idempotent validation and extraction jobs with leases, state versions, bounded attempts, backoff, and permanent/transient failure classification.
- Deterministic fixture extractor, local Tesseract adapter, manual fallback, and provider-agnostic optional LLM adapter.
- Separate encrypted raw OCR output, structured per-field values, confidence, source, normalized value, and validation errors.
- Feature flag, explicit per-document consent, redaction, strict JSON Schema validation, timeout, retry, and input/output limits for external AI.
- Reconciliation command for work stranded during Redis failure.

Acceptance gate:

- State-transition, retry, stale-worker, duplicate-job, and exhausted-attempt tests pass.
- Deterministic and Tesseract fixture contracts pass without network access.
- The prompt-injection fixture cannot change provider selection, execute commands, invoke tools, or activate a deadline.
- A Redis outage preserves pending database work and processing resumes after recovery.

## Phase 5 — Human review and usable frontend

Deliverables:

- Extraction review that exposes each field, confidence, validation errors, and correction controls.
- One atomic confirmation path that updates the authoritative purchase, calculates deadlines, records explanations, schedules reminders, and transitions the document to ready.
- Responsive Vue pages for landing, authentication, dashboard, purchases, upload, review, details/timeline, household members, preferences, privacy, deletion, and demo mode.
- Keyboard navigation, visible focus, form labels/errors, sufficient contrast, reduced-motion support, and loading/empty/error/offline-like states.

Acceptance gate:

- Unconfirmed extraction never creates an active deadline or reminder.
- Confirmation idempotency and rollback tests pass.
- Component, validation, API error, and accessibility tests pass.
- Production frontend build and strict TypeScript check pass.

## Phase 6 — Reminders, portability, and deletion

Deliverables:

- In-app and email reminders generated from versioned deadlines and user intervals.
- Database uniqueness, atomic claims, bounded retries, and a delivery-unknown state for ambiguous SMTP outcomes.
- ICS download, claim ZIP with structured data and available receipt files, account export, and owner-only household export.
- Recent-password protection and audit events for bulk export and account deletion.
- Idempotent account-deletion workflow that removes private objects before database identities and blocks unsafe deletion of a shared owned household.
- Backup, restore, migration, and demo-reset procedures.

Acceptance gate:

- Reminder scheduling, timezone, quiet-hour behavior if enabled, and deduplication tests pass.
- ICS parses in a standards-aware test.
- Claim and account archives contain the documented data and no data from an unauthorized household.
- Export URLs expire and are absent from logs.
- Account deletion removes every indexed object and relational record covered by the request, including retry after an injected partial failure.

## Phase 7 — Security and failure testing

Deliverables:

- OWASP ASVS Level 1 evidence checklist and threat model.
- Security headers, request limits, CSP, safe output handling, rate limits, dependency audit, and secret scanning.
- Failure tests for unavailable Redis, storage, mail, and OCR.
- Tests for stored XSS payloads, mass assignment, IDOR, signed-link leakage, and sensitive-log redaction.

Acceptance gate:

- Backend formatter, strict static analysis, tests, Composer validation, and dependency audit pass.
- Frontend format, lint, typecheck, unit/accessibility tests, build, and configured dependency audit pass.
- Security test suite passes without weakening assertions or excluding the main flow.

## Phase 8 — End-to-end proof, CI, and release candidate

Deliverables:

- Reproducible synthetic demo account and dataset.
- Playwright flow covering registration, household creation, receipt upload, processing, review, deadline, reminder, ICS export, and cross-tenant denial.
- Current screenshots generated from the seeded application.
- GitHub pull-request and main/release workflows with least privilege, pinned actions, Docker validation, dependency review, CodeQL where supported, SBOM, and versioned artifacts.
- README, architecture, case study, recruiter tour, security policy, operations guidance, and maintainer notes reconciled to implemented behavior.
- One read-endpoint and one upload-related load scenario.

Acceptance gate:

- setup, test, quality, E2E, seed/demo, and production-build commands pass from a clean state.
- CI invokes the same underlying commands used locally.
- Performance results are published only after a real run with environment and limitations.
- Screenshot links, API links, demo credentials, and recruiter-tour file links resolve.
- Known limitations and failed or changed implementation choices are documented honestly.

## Stretch gate

Stretch work begins only after Phase 8 passes in full. Priority is:

1. Symfony integration example.
2. Yii integration example.
3. Optional isolated FastAPI OCR service, only if profiling demonstrates a useful boundary.
4. Expiring read-only claim link.
5. Signed webhooks.
6. Additional locale.
7. Web push notifications.

Until then, each stretch item is an issue with acceptance criteria and no partial runtime code.

## Release evidence

The release report must list exact commands and exit results, local URLs, synthetic demo credentials, measured coverage where available, and any failing or skipped check. KeepProof should be described as a production-oriented portfolio project. Real operation would additionally require managed secrets and backups, recovery exercises, monitored email delivery, storage durability validation, malware controls appropriate to the threat model, incident response, and jurisdiction-specific privacy review.
