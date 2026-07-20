# Maintainer Notes

These are concise, code-specific answers for maintenance and interview discussion. They describe the current repository, not a hypothetical architecture.

## Why Laravel?

The product needs authentication, CSRF, signed verification, password reset, validation, authorization policies, queues, scheduling, mail, notifications, S3, database transactions, API resources, and testing. Laravel provides those as maintained primitives on the project's primary PHP stack. The implementation still keeps central date logic in an independent package and uses explicit actions at complex boundaries, so the framework accelerates infrastructure without owning every business rule.

Relevant files: [`bootstrap/app.php`](../apps/backend/bootstrap/app.php), [`AppServiceProvider.php`](../apps/backend/app/Providers/AppServiceProvider.php), and [`composer.json`](../apps/backend/composer.json).

## Why a modular monolith?

The most important write—human confirmation—updates purchase data/items, calculates deadlines, creates reminders, records accepted extraction fields, transitions the receipt, creates an idempotency record, and writes an audit event. Those changes benefit from one PostgreSQL transaction. Network services would replace that property with distributed coordination before any independent data ownership or load need exists.

HTTP, queue, and scheduler already scale as separate processes. Capability directories and the Deadline Engine package provide seams without forcing calls over a network.

Relevant files: [`ConfirmExtraction.php`](../apps/backend/app/Modules/Documents/Actions/ConfirmExtraction.php) and [ADR 0001](adr/0001-modular-monolith.md).

## Where are the transaction boundaries?

- Registration: user + personal household + owner membership + audit.
- Manual create/update: purchase + items + calculated deadlines + reminder rows + audit.
- Upload: after S3 succeeds, one transaction creates a draft if needed, document, initial/queued transition records, idempotency record, and audit. Queue publication is after commit.
- Processing: S3/OCR runs outside a transaction; a short locked transaction writes encrypted OCR metadata, replaces candidate fields, and moves to `needs_review`.
- Confirmation: authoritative purchase/items/deadlines/reminders + accepted fields + `ready` transition + idempotency + audit.
- Reminder: a short lock claims `sending`, transport runs outside the transaction, then the terminal status is written.
- Deletion: private objects are deleted first; sessions and the user cascade are then deleted transactionally.

No transaction stays open during S3, OCR, SMTP, or external HTTP work. See [Architecture / Transaction boundaries](ARCHITECTURE.md#transaction-boundaries).

## How is idempotency enforced?

Receipt upload and extraction confirmation require a caller-supplied `Idempotency-Key`. The application stores only its SHA-256 hash with user, operation, resource ID, and expiry. A unique `(user_id, operation, key_hash)` constraint prevents races; a replay returns the original tenant-scoped resource.

Jobs separately defend against at-least-once delivery: terminal-state checks, row locks, the controlled state machine, `WithoutOverlapping`, deterministic in-app notification UUIDs, and unique reminder deduplication keys. Idempotency is specific to each side effect rather than a generic middleware claim.

Relevant files: [`UploadReceipt.php`](../apps/backend/app/Modules/Documents/Actions/UploadReceipt.php), [`ConfirmExtraction.php`](../apps/backend/app/Modules/Documents/Actions/ConfirmExtraction.php), and the document-processing migration.

## What happens if Redis fails?

PostgreSQL remains authoritative and sessions use PostgreSQL, not Redis. If queue publication fails after an upload, the document remains `validation_pending`, its dispatch lease is cleared, and the response exposes a safe delayed-processing message. The scheduler scans missing/stale leases every minute and republishes after Redis returns.

Existing workers pause while Redis is unavailable. Cache/throttle-dependent requests can fail safely; the readiness endpoint reports Redis failure because cache/queue are configured to require it. Once Redis returns, the worker and reconciliation resume from database state. There is no claim that every user-facing request continues during the outage.

Relevant files: [`DispatchReceiptProcessing.php`](../apps/backend/app/Modules/Documents/Actions/DispatchReceiptProcessing.php), [`ReconcileReceiptProcessing.php`](../apps/backend/app/Modules/Documents/Actions/ReconcileReceiptProcessing.php), and [`ReadinessController.php`](../apps/backend/app/Modules/Shared/Http/Controllers/ReadinessController.php).

## How is duplicate reminder delivery prevented?

Scheduling derives a SHA-256 key from deadline, user, channel, interval, and current due timestamp; the database enforces uniqueness. The job locks the reminder, skips sent/cancelled/delivery-unknown rows, and uses `WithoutOverlapping`. In-app delivery has a deterministic UUIDv5, so a previously committed notification can be detected and the reminder repaired to `sent`.

SMTP has an unavoidable ambiguity if the process dies around handoff. A stale `sending` email becomes `delivery_unknown` and is not resent automatically. That prevents a possible duplicate at the cost of operational review.

Relevant files: [`SchedulePurchaseReminders.php`](../apps/backend/app/Modules/Notifications/Actions/SchedulePurchaseReminders.php), [`DispatchDueReminders.php`](../apps/backend/app/Modules/Notifications/Actions/DispatchDueReminders.php), and [`SendReminder.php`](../apps/backend/app/Modules/Notifications/Jobs/SendReminder.php).

## How are receipt files protected?

- Only content-verified JPEG, PNG, and PDF up to 10 MiB are accepted.
- Images are decoded/re-encoded and capped at 25 megapixels, removing EXIF/ancillary metadata.
- The application generates an opaque key; the original name cannot affect the path.
- Objects use private S3 visibility and a SHA-256 integrity value.
- The worker verifies checksum/signature and quarantines PDFs with known active/embedded-content markers.
- An authorized POST creates a short-lived signed URL; quarantined or removed originals return 404.
- Signed URLs, storage keys, OCR text, and document contents are redacted from structured logs.
- Account deletion removes owned private objects before removing relational identity; original deletion is also available per receipt.

The current product has no antivirus/CDR service and no explicit upload-time PDF page-count rejection; those are documented production limitations.

Relevant files: [`SanitizeReceiptUpload.php`](../apps/backend/app/Modules/Documents/Actions/SanitizeReceiptUpload.php), [`ValidateReceiptContent.php`](../apps/backend/app/Modules/Documents/Actions/ValidateReceiptContent.php), and [ADR 0005](adr/0005-private-s3-compatible-storage.md).

## How is a tenant data leak prevented?

Every tenant route begins with a household ID that is resolved through `request()->user()->households()`. Nested purchase/document bindings query through that resolved household, not globally. Policies then check membership/owner permissions. Tenant-owned rows carry `household_id`, and their main indexes/foreign keys preserve scope and integrity.

Tests create real resources in two households, pass valid foreign ULIDs, and expect non-disclosing 404 responses. The connected Playwright test repeats that proof through Nginx, cookies, PostgreSQL, and the actual HTTP stack. ULID entropy is never used as the permission check.

Relevant files: [`AppServiceProvider.php`](../apps/backend/app/Providers/AppServiceProvider.php), [`HouseholdAuthorizationTest.php`](../apps/backend/tests/Feature/Households/HouseholdAuthorizationTest.php), and [`connected-flow.spec.ts`](../apps/frontend/e2e/connected-flow.spec.ts).

## Why is AI not the source of truth?

Receipt OCR can confuse dates, totals, and policy terms; model confidence does not establish correctness. Extraction therefore writes only candidate fields and moves the document to `needs_review`. The UI exposes value, normalized value, confidence, source, and errors. No deadline/reminder is active until an authorized user confirms corrected values.

The optional provider is also off by default, per-document consent-gated, redacted, schema/allowlist/budget validated, and unable to choose tools or execute receipt instructions. Manual entry remains complete.

Relevant files: [`SchemaValidatedLlmReceiptExtractor.php`](../apps/backend/app/Modules/Documents/Extraction/SchemaValidatedLlmReceiptExtractor.php), [`ConfirmExtraction.php`](../apps/backend/app/Modules/Documents/Actions/ConfirmExtraction.php), and [`PromptInjectionTest.php`](../apps/backend/tests/Unit/Documents/PromptInjectionTest.php).

## How would extraction scale?

First scale existing `documents` workers independently and use queue-age, processing-time, CPU, memory, and failure-code measurements. Separate queues can isolate PDF/Tesseract from lightweight deterministic work. Object storage and PostgreSQL already provide process-independent boundaries.

Only if profiling shows a meaningful runtime, resource, fault-isolation, or team-ownership need should OCR move behind a service. That service should remain stateless, accept an object reference or bounded bytes over authenticated transport, return the same typed extraction contract, preserve correlation IDs, and never own purchase/deadline truth. The Laravel state machine, consent, validation, and confirmation boundary remain authoritative.

## How would storage be migrated?

1. Freeze or dual-record new object writes for a bounded migration window.
2. Copy private objects from the old S3-compatible bucket to the target using exact keys and encryption/retention policy.
3. Compare object counts, sizes, and stored SHA-256 values against PostgreSQL; sample full reads.
4. Test signed downloads, claim exports, deletion, and readiness in an isolated environment.
5. Switch Flysystem endpoint/credentials, monitor reads, then keep the old bucket read-only for the approved rollback period.
6. Destroy the old copy only after retention, backup, and erasure requirements are satisfied.

The application is deliberately written against the S3/Flysystem boundary and stores `storage_disk` + `storage_key`, not SeaweedFS-specific URLs.

## How are backups verified and restored?

Redis is excluded because its jobs/locks are reconstructible. PostgreSQL and private objects are one logical backup set with a recorded cut time. Restore objects and database into an isolated environment, configure non-production mail, run forward-only migrations, prepare the storage readiness marker, verify counts/checksums and tenant-specific sample archives, then run `keepproof:receipts:reconcile` and the smoke suite.

A backup is not considered useful merely because files exist; the restore procedure and evidence checks matter. This repository documents the process but does not claim a monitored off-site recovery drill.

See [Backup and Restore](operations/BACKUP_RESTORE.md).

## Which parts should become services at real growth?

The first candidate is resource-heavy OCR/extraction because it has a clear stateless contract, independent CPU/memory profile, and asynchronous failure boundary. Large export generation could become a separate worker pool, not necessarily a network service. Notification provider adapters may split operationally if delivery volume/compliance needs independent ownership.

Accounts, Households, Purchases, and deadline confirmation should stay together until there is genuine independent data ownership; separating them would weaken useful transactions. The Deadline Engine is already independently publishable without becoming a service.
