# KeepProof Case Study

## Context

KeepProof is an open-source, production-oriented portfolio project that demonstrates a complete PHP backend problem rather than an isolated CRUD sample. It combines private document handling, household tenancy, calendar-domain rules, asynchronous work, notifications, portability, and a separate Vue SPA in one locally reproducible product.

There are no claimed customers, revenue, conversion improvements, production traffic, or business-impact percentages. Product screenshots, timings, and test results come from synthetic local data.

## User problem

People often discover a return or warranty requirement when the useful date is close or already past. Proof is fragmented across paper, inboxes, downloads, and camera rolls; the policy period is detached from the receipt; and a tool that silently guesses the wrong date can be worse than a manual calendar entry.

The useful outcome is not “OCR a receipt.” It is: retain evidence privately, make the relevant date understandable, remind the right household, and assemble portable proof when action is needed.

## Product constraints

- The complete path must work locally without a paid API.
- Receipts may contain names, addresses, order IDs, payment fragments, and purchase habits.
- OCR quality is uncertain and confidence is not correctness.
- Return/warranty terms are merchant/user evidence, not a maintained law database.
- Household records require strong tenant isolation even if a valid ULID is guessed.
- Redis, object storage, OCR, and SMTP can fail independently of PostgreSQL.
- The repository must remain understandable to one maintainer and runnable on a new machine.
- Public documentation must distinguish verified local evidence from production claims.

## Goals and non-goals

### Goals

- A verified user can upload a synthetic receipt, review extracted fields, confirm a purchase, receive deadlines/reminders, export ICS and a claim ZIP, and export/delete account data.
- Core return/warranty calculations are framework-agnostic and explainable.
- Document and notification work is bounded, idempotent, and recoverable.
- Files are private by default and are never addressed by user-controlled paths.
- The SPA and API share one OpenAPI contract and one same-site authentication model.
- Security, failure behavior, operations, and trade-offs are visible in code and tests.

### Non-goals

- Legal advice or country-specific consumer-law automation.
- A universal rules engine, microservice estate, Kafka, event sourcing, CQRS, GraphQL, Kubernetes, billing, or native mobile clients.
- Exactly-once SMTP delivery.
- Handwriting-grade or arbitrary-layout OCR.
- A Python service without measured resource-isolation value.
- Symfony/Yii examples before the core release-candidate gates are stable.

## Architecture

The system is a Laravel modular monolith backed by one PostgreSQL database. HTTP, queue, and scheduler are separate processes built from the same code. Vue and TypeScript form an independent SPA behind the same Nginx origin. Redis is used for queues, cache, and overlap locks; SeaweedFS provides a local S3-compatible private bucket; Mailpit captures development email; Tesseract is installed with the Laravel worker.

Capability modules are Accounts, Households, Purchases, Documents, Notifications, Exports, and Shared infrastructure. Controllers authorize and delegate to verb-named actions. Eloquent is used directly where it is the actual persistence abstraction. The independent Deadline Engine is the main domain package.

This shape keeps the review-confirmation path in one database transaction while leaving real infrastructure/provider boundaries replaceable. See [Architecture](ARCHITECTURE.md) for data flow, transactions, failure modes, and scaling.

## Key engineering decisions

### Modular monolith over microservices

Confirmation crosses documents, purchases, deadlines, reminders, idempotency, and audit. A single transaction is a product feature here. Splitting these writes across services would introduce compensation, versioned messages, eventual-consistency UI, and more credentials without a demonstrated independent scaling need.

Runtime processes can still scale separately. The source boundary remains explicit enough to extract OCR later if profiling and ownership justify it.

### A narrow, independent Deadline Engine

`packages/deadline-engine` accepts immutable purchase-time, period, counting-convention, timezone, and manual-override values. It returns effective dates plus stable rule codes, summaries, and trace steps. It owns calendar arithmetic only; retailer policies, storage, notifications, and legal interpretations remain in Laravel or with the user.

The package tests calendar-day/month/year behavior, purchase-day inclusive/exclusive semantics, leap dates, end-of-month rules, DST gaps/overlaps, invalid local times, manual overrides, determinism, and ordering invariants without booting Laravel.

### Human confirmation before activation

OCR results are stored as candidate fields with raw value, normalized value, confidence, source, and validation errors. Raw OCR text is encrypted separately. A candidate never creates an active deadline.

An authorized confirmation with an idempotency key updates the purchase/items, calculates and snapshots deadline explanations, reconciles reminders, stores accepted values, transitions the document to `ready`, and audits the action inside one database transaction. This is the source-of-truth boundary.

### Stateful-cookie REST API

Sanctum stateful cookies keep bearer tokens out of browser storage. CSRF, database sessions, exact CORS origins, signed email verification, password reset, recent-password confirmation, and throttling use Laravel primitives. OpenAPI 3.1 is the tracked contract; generated TypeScript types prevent the SPA from inventing response shapes.

Top-level email verification navigation required one deliberate exception: the signed `/api/v1/email/verify/...` URL uses the `web` session middleware because email navigation does not carry the `Origin`/`Referer` signals Sanctum uses to identify a stateful SPA request.

### Private S3-compatible evidence

The backend content-sniffs uploads, enforces 10 MiB and image-dimension limits, re-encodes images, derives safe display names, calculates SHA-256, and generates the object key. The original filename never controls storage. Objects are private; authorized downloads create short-lived signed URLs; quarantined/deleted originals are denied.

S3 and PostgreSQL cannot commit together. Upload sanitizes and writes the object, then commits relational state; a database exception triggers compensating object deletion. Queue publication happens after the durable row. This is simpler and more honest than pretending a distributed transaction exists.

## Asynchronous processing

The document state machine permits only enumerated transitions and journals each move with a reason and correlation ID under a row lock. `ProcessReceipt` has three attempts, explicit backoff, timeouts, `WithoutOverlapping`, terminal-state guards, and permanent/transient exception classes.

Redis is a wake-up channel, not the authoritative record:

1. Upload commits `validation_pending` and a dispatch lease in PostgreSQL.
2. Queue publication is attempted after commit.
3. A failed publication clears the lease and leaves a user-safe delayed state.
4. Every minute, reconciliation locks missing/stale leases and republishes them.
5. The job re-checks database state, reads and validates the object, runs extraction outside a transaction, then atomically writes fields and `needs_review`.

This design handles a narrow lost-dispatch window without a decorative outbox. If requirements later demand broader guaranteed event delivery, an outbox would need a relay, ordering rules, cleanup, monitoring, and consumer idempotency—not just an `outbox_events` table.

Reminder jobs use database deduplication keys and deterministic in-app notification IDs. Email has an additional `delivery_unknown` state: after a crash near SMTP handoff, KeepProof will not blindly resend and claim exactly-once delivery.

## AI safety and privacy

Local-first is the default behavior, not a landing-page promise layered over a mandatory cloud call.

- Deterministic fixture extraction provides reproducible tests and demo output.
- Tesseract provides local OCR for printed JPG/PNG/PDF input; PDFs render only the first three pages.
- Pattern extraction covers the known structured field boundary.
- Manual entry and correction complete the entire user flow without OCR.
- The optional LLM adapter is disabled by default and requires feature configuration plus per-document consent.

Before an external request, the adapter truncates and redacts receipt text. It sends a provider-neutral JSON contract over HTTPS (localhost is permitted only for local/test), uses connection/response timeouts and bounded retry, sets input/output/token/cost/response limits, accepts only allowlisted fields, and validates a strict JSON Schema plus usage accounting. It stores provider/model and prompt version, not chain of thought.

Receipt text is never passed to a shell, evaluator, route, tool runner, or provider-selection mechanism. A synthetic prompt-injection receipt asks the system to ignore instructions and run a command; the contract test proves it remains ordinary text and cannot change system behavior or activate a deadline.

## Security model

The highest risks are account takeover, cross-household IDOR, private object disclosure, malicious uploads, unsafe extraction, sensitive logs, and incomplete erasure.

Implemented controls include:

- Argon/Bcrypt behavior delegated to Laravel's current password hashing configuration, strict password validation, session rotation/revocation, email verification, recovery token broker, recent-password gates, and layered throttles.
- Stateful CSRF protection, exact credentialed CORS, production `Secure`/`HttpOnly`/`SameSite=Lax` host cookie configuration, CSP and baseline response headers.
- Tenant-first route binding, nested household queries, Policies, database foreign keys, and cross-tenant tests using real valid identifiers.
- MIME-from-content checks, re-encoding, checksum/signature verification, known active-PDF rejection, opaque keys, private visibility, and 1–15 minute signed URLs.
- Eloquent/query-builder parameterization, request objects, guarded fillable attributes, Vue text interpolation, and CSP instead of raw receipt HTML.
- JSON log redaction for receipt content, OCR, signed URLs, object keys, credentials, cookies, tokens, emails, and payment-like identifiers.
- Recent-password and audit controls for account export/deletion, object-first erasure, and a shared-household ownership block.

The ASVS document is evidence-linked but is not an ASVS certification, penetration test, or legal compliance statement. Malware scanning/CDR, external audit, incident response, key rotation automation, and jurisdictional privacy review remain deployment requirements.

## Testing strategy

The pyramid follows consequence rather than framework layer.

### Domain/package

- Deadline Engine table tests for exact examples.
- Property-style loops for determinism, monotonicity, boundary order, leap years, and timezone transitions.
- Pure unit tests for extraction mapping, redaction/provider contracts, roles, structured logging, and prompt injection.

### Laravel feature/integration

- Authentication, CSRF, verification navigation, password reset/change, session revocation, registration rollback, and audit.
- Owner/member authorization and tenant isolation for household, purchase, document, dashboard, exports, and nested valid foreign ULIDs.
- Purchase validation, integer money, filters/pagination, deadline mapping, timezone boundaries, and query counts.
- Upload spoofing, MIME, pixel limits, EXIF removal, PDFs, signed links, storage compensation, idempotency, state transitions, retries, stale jobs, and quarantine.
- Confirmation rollback/idempotency and the invariant that provisional extraction creates no active deadline.
- Reminder deduplication, quiet hours, retry, interruption, deterministic in-app delivery, and ambiguous email handling.
- ICS parsing, claim/account ZIP contents, export/deletion reauthentication, tenant scope, and storage failure behavior.
- OpenAPI representative contract, health checks, private storage preparation, and deterministic seeding.

### Frontend and browser

- Vue component behavior, form validation, money parsing, API Problem Details handling, session hydration, and download states.
- ESLint, strict TypeScript, OpenAPI lint/generation check, production build, and high-severity npm audit.
- Playwright desktop/mobile demo flows, privacy/destructive confirmation, automated axe smoke, current visual references, and a connected infrastructure flow.

The connected E2E registers and verifies two real accounts, uploads the synthetic receipt through private storage, waits for the Redis worker, reviews and confirms extraction, checks the calculated deadline/reminder, downloads and inspects ICS/ZIP artifacts, then proves the second household receives 404 for the first household and purchase.

### Latest verified local results

On 2026-07-20:

- A clean `reset` followed by `setup` completed in 188.4 seconds, including dependency installation, fresh PostgreSQL migrations, and the private-storage sentinel check.
- The root quality gate completed in 89.8 seconds: Compose origin contracts, Composer validation and audit, Pint across 182 files, PHPStan/Larastan with no errors, 92 backend tests with 697 assertions, and both real Tesseract PNG/PDF contract tests passed inside the backend container.
- Deadline Engine: Composer validation, PHPStan, and audit passed; 34 tests executed 46,276 assertions.
- Frontend: Prettier, Redocly OpenAPI lint, generated-client drift check, ESLint, strict Vue TypeScript, 8 test files with 23 tests, production build, and the high-severity npm audit all passed.
- The current Playwright run passed 12/12 tests in 40.9 seconds; the connected receipt lifecycle took 13.1 seconds and the public-origin session regression took 4.2 seconds. The complete wrapper, including browser setup, both projects, and restoration of the normal development origin, completed in 115.3 seconds.
- The standalone root test command passed in 44.4 seconds. The production images built successfully, the non-root Nginx configuration passed `nginx -t`, and an ephemeral production-ingress smoke request returned HTTP 200.

No coverage percentage is published because this run did not measure one. High assertion counts in the property tests are evidence of exercised inputs, not a substitute for risk-based review. The maintained risk-to-test mapping is in [Testing Strategy](TESTING.md).

## Performance measurements

A dependency-free Node harness authenticates through Sanctum/CSRF and measures an indexed dashboard read plus a real multipart private receipt upload. On the recorded Docker Desktop development run:

- 50 dashboard reads at concurrency 5: 15.52 requests/second, p50 314.4 ms, p95 366.8 ms, zero failures.
- 5 private uploads at concurrency 1: 7.18 requests/second, p50 107.4 ms, p95 261.6 ms, zero failures.

The upload measurement stops at `202 Accepted` after sanitization, S3 persistence, relational/idempotency/audit state, and dispatch intent. It excludes asynchronous OCR. The backend used the local PHP server on one machine, so these figures are a regression baseline, not capacity, SLA, or production traffic evidence.

Hardware, dataset, exact command, all latency values, and limitations are in [Local Performance Baseline](performance/LOCAL_BASELINE.md).

## Trade-offs

- **One database enables useful atomicity but creates a shared scaling boundary.** That is appropriate for the current workflow and team size.
- **S3 compensation is explicit but not atomic.** A production deployment needs orphan detection and a tested restore/erasure procedure.
- **Database state plus lease reconciliation covers document dispatch, not every possible event.** It avoids outbox complexity while the number of recoverable async intents is small.
- **Human review reduces automation speed.** It is retained because deadline correctness and user trust matter more than removing one step.
- **Local OCR improves privacy but is less accurate than some managed services.** Manual entry and field-level review keep the product functional.
- **Stateful cookies optimize the first-party browser.** A future mobile/third-party client would require a separately designed token boundary.
- **Synchronous ZIP export is simple but bounded by request time/memory.** Large household exports should become persisted, encrypted, expiring async jobs.

## What failed or changed during implementation

These changes came from running the system rather than polishing the intended diagram:

1. **Redis publication was initially too optimistic.** A database row could be committed while queue dispatch failed. A database dispatch lease and minute reconciliation command made the durable intent recoverable.
2. **Email retries could duplicate ambiguous deliveries.** The reminder model gained `sending` and `delivery_unknown`; uncertain email is held for operator reconciliation while in-app writes remain safely retryable.
3. **A clean frontend dependency volume was root-owned.** `npm ci` failed with `EACCES` even though an existing developer volume worked. The development image now prepares ownership before switching to the non-root Node user.
4. **Laravel's local server reload wrapper filtered Compose environment values when an application `.env` existed.** HTTP used stale SQLite/session configuration while CLI commands used PostgreSQL. Development now runs `artisan serve --no-reload`, keeping Compose as the process environment.
5. **Nginx cached container IPs across backend recreation.** Docker DNS resolution is now dynamic in development and production configs.
6. **Internal Playwright HTTP is not a secure context.** `crypto.randomUUID()` existed in normal localhost browsers but not at `http://nginx`; the client now has a tested `getRandomValues` UUIDv4 fallback used by correlation IDs, upload idempotency, and local item IDs.
7. **Signed email navigation did not look like a Sanctum XHR.** It had no origin/referrer and could not restore the API middleware session. The signed GET moved to the web-session middleware while retaining its `/api/v1` URL and signature.
8. **The E2E test itself assumed uppercase ULIDs and ambiguous label matching.** Production emits lowercase ULIDs, and the confidence badge shares field terminology. The test now validates the alphabet case-insensitively and selects the date textbox by accessible role.
9. **A notification route could corrupt subsequent SPA rendering.** The settings page passed a Vue reactive proxy to `structuredClone()`, which raises `DataCloneError` in browsers and left the nested router view empty. The form now takes an explicit typed snapshot, with component coverage and desktop/mobile navigation regression tests that assert every primary destination renders without a page error.
10. **A separate FastAPI OCR service remained unjustified.** Local process adapters gave the needed timeout, command-array, queue, and image boundary with fewer deployment and privacy paths.

## Lessons learned

- Clean-state and connected tests find environment contracts that unit tests cannot: volume ownership, internal host security context, signed-link host/port generation, container DNS, and process environment inheritance.
- “Idempotent job” is incomplete unless the durable intent to dispatch can also be recovered.
- Exactly-once is a poor claim at an SMTP boundary. Modeling uncertainty is safer than hiding it behind retries.
- Confidence UI is useful only when machine data remains visibly provisional and easy to correct.
- A standalone package earns its boundary through independent semantics and tests, not simply through a separate directory.
- Privacy claims become credible when they map to defaults, provider gates, storage visibility, log processors, fixtures, exports, deletion, and tests.
- Documentation must be reconciled after execution. Planned object paths, page limits, or async exports should not survive in ADRs if the code took a narrower approach.

## What would change at larger scale

1. Put production PHP-FPM replicas behind managed TLS/load balancing and retain database sessions or a deliberately selected session tier.
2. Use managed PostgreSQL with point-in-time recovery, monitored replicas, slow-query telemetry, and rehearsed restore objectives.
3. Use a durable S3 provider with lifecycle/versioning policy, malware/CDR controls, retention/erasure evidence, and inventory-based orphan detection.
4. Scale workers per queue and resource class; isolate OCR into a service only if profiling shows CPU/memory/runtime or team-ownership benefits.
5. Turn account/claim exports into encrypted, expiring async artifacts when size distribution warrants it.
6. Add metrics, traces, queue-age/dead-letter alerts, reminder-delivery dashboards, and privacy-aware log retention.
7. Introduce an outbox/relay if the number and importance of cross-boundary events exceed the current explicit lease patterns.
8. Add a deployment-specific secrets manager, key rotation, incident response, vendor review, data retention policy, recovery exercises, and legal/privacy review.
9. Split a module only after it has independent data ownership and a measured scaling or release-cadence need. The first likely candidate is resource-heavy extraction, not Accounts or Purchases.

KeepProof is therefore described as a **production-oriented portfolio project**, not a production-ready hosted service.
