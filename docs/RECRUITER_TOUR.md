# KeepProof Recruiter Tour

This route shows the product and its strongest engineering decisions in roughly 5–7 minutes. Every link points to implemented code or a verified artifact.

## Minute 0–1: See the product

Start with the [README](../README.md), especially the current [dashboard screenshot](assets/screenshots/dashboard.png), receipt-processing sequence, and one-command local setup.

The product path is concrete: private receipt → asynchronous extraction → field-level human review → explainable return/warranty dates → reminders and portable proof. There is no paid API requirement and no legal-rights database.

If Docker is already available:

```bash
make setup
make demo
```

Open <http://localhost:8080> and choose **Try the local demo**, or sign in with the synthetic credentials from the README.

## Minute 1–2: Review the central PHP domain code

Open the standalone [`DeadlineCalculator`](../packages/deadline-engine/src/DeadlineCalculator.php), then its immutable request/result objects under [`src/Value`](../packages/deadline-engine/src/Value/) and the boundary-heavy tests:

- [`DeadlineCalculatorTest`](../packages/deadline-engine/tests/Unit/DeadlineCalculatorTest.php)
- [`BoundaryPropertyTest`](../packages/deadline-engine/tests/Unit/BoundaryPropertyTest.php)
- [`TimezoneBehaviorTest`](../packages/deadline-engine/tests/Unit/TimezoneBehaviorTest.php)

Look for explicit inclusive/exclusive semantics, calendar month/year behavior, manual overrides, local timezone validation, rule codes, and explanation traces. The package has no Laravel dependency and runs its own Composer validation, PHPStan, tests, and audit.

Why it matters: this is business logic with failure cases and stable outputs, not a controller helper or generic rules-engine exercise.

## Minute 2–3: Follow asynchronous receipt processing

Read these in order:

1. [`UploadReceipt`](../apps/backend/app/Modules/Documents/Actions/UploadReceipt.php) — sanitization, opaque S3 key, compensation, relational transaction, idempotency, and post-commit dispatch.
2. [`DispatchReceiptProcessing`](../apps/backend/app/Modules/Documents/Actions/DispatchReceiptProcessing.php) — durable dispatch lease around Redis publication.
3. [`ProcessReceipt`](../apps/backend/app/Modules/Documents/Jobs/ProcessReceipt.php) — overlap lock, bounded attempts/backoff/timeout, permanent vs transient failures, S3/OCR outside the write transaction, and idempotent terminal guards.
4. [`TransitionDocumentState`](../apps/backend/app/Modules/Documents/Actions/TransitionDocumentState.php) — the only allowed state transitions plus journal rows under a database lock.
5. [`ReconcileReceiptProcessing`](../apps/backend/app/Modules/Documents/Actions/ReconcileReceiptProcessing.php) — reconstruction of lost Redis wake-ups from PostgreSQL.
6. [`ConfirmExtraction`](../apps/backend/app/Modules/Documents/Actions/ConfirmExtraction.php) — the transaction where provisional fields become an active purchase, deadlines, and reminders.

The failure and concurrency tests are in [`ReceiptWorkflowTest`](../apps/backend/tests/Feature/Documents/ReceiptWorkflowTest.php).

## Minute 3–4: Inspect authorization and privacy boundaries

Tenant isolation begins in [`AppServiceProvider`](../apps/backend/app/Providers/AppServiceProvider.php): household IDs are resolved through the authenticated user's memberships, and nested purchases/documents are resolved through that household. Policies remain explicit:

- [`HouseholdPolicy`](../apps/backend/app/Modules/Households/Policies/HouseholdPolicy.php)
- [`PurchasePolicy`](../apps/backend/app/Modules/Purchases/Policies/PurchasePolicy.php)
- [`ReceiptDocumentPolicy`](../apps/backend/app/Modules/Documents/Policies/ReceiptDocumentPolicy.php)

Then inspect [`HouseholdAuthorizationTest`](../apps/backend/tests/Feature/Households/HouseholdAuthorizationTest.php). It uses valid IDs from another tenant and expects non-disclosing failures; ULID unpredictability is not treated as authorization.

For files, read [`SanitizeReceiptUpload`](../apps/backend/app/Modules/Documents/Actions/SanitizeReceiptUpload.php), [`ValidateReceiptContent`](../apps/backend/app/Modules/Documents/Actions/ValidateReceiptContent.php), and the signed-download action in [`ReceiptDocumentController`](../apps/backend/app/Modules/Documents/Http/Controllers/ReceiptDocumentController.php).

Security evidence is indexed in [OWASP ASVS Level 1](security/ASVS_LEVEL_1.md) and the [Threat Model](security/THREAT_MODEL.md). Responsible disclosure is in [`SECURITY.md`](../SECURITY.md).

## Minute 4–5: Check API and optional AI design

The public contract is [`openapi.yaml`](../apps/backend/openapi/openapi.yaml); the generated SPA types are in [`apps/frontend/src/api/generated`](../apps/frontend/src/api/generated/). Routes are versioned in [`routes/api.php`](../apps/backend/routes/api.php), and Problem Details/correlation behavior is configured in [`bootstrap/app.php`](../apps/backend/bootstrap/app.php).

For the AI boundary, compare the interfaces and implementations:

- [`DocumentTextExtractor`](../apps/backend/app/Modules/Documents/Contracts/DocumentTextExtractor.php)
- [`ReceiptDataExtractor`](../apps/backend/app/Modules/Documents/Contracts/ReceiptDataExtractor.php)
- [`TesseractDocumentTextExtractor`](../apps/backend/app/Modules/Documents/Extraction/TesseractDocumentTextExtractor.php)
- [`DeterministicFixtureExtractor`](../apps/backend/app/Modules/Documents/Extraction/DeterministicFixtureExtractor.php)
- [`SchemaValidatedLlmReceiptExtractor`](../apps/backend/app/Modules/Documents/Extraction/SchemaValidatedLlmReceiptExtractor.php)
- [`receipt-extraction-response.schema.json`](../apps/backend/resources/schemas/receipt-extraction-response.schema.json)

The external adapter is feature-flagged, consent-gated, redacted, budgeted, HTTPS-constrained, strict-schema validated, and unable to activate a deadline. [`PromptInjectionTest`](../apps/backend/tests/Unit/Documents/PromptInjectionTest.php) and [`ExternalAiReceiptExtractorTest`](../apps/backend/tests/Unit/Documents/ExternalAiReceiptExtractorTest.php) make those claims reviewable.

## Minute 5–6: See reminders, exports, and failure honesty

- [`SchedulePurchaseReminders`](../apps/backend/app/Modules/Notifications/Actions/SchedulePurchaseReminders.php) creates database-deduplicated, timezone-aware schedules and moves email out of configured quiet hours.
- [`SendReminder`](../apps/backend/app/Modules/Notifications/Jobs/SendReminder.php) distinguishes safe in-app retry from ambiguous SMTP delivery.
- [`ReminderWorkflowTest`](../apps/backend/tests/Feature/Notifications/ReminderWorkflowTest.php) covers duplicate prevention, interruption, and retry behavior.
- [`ExportClaimPackage`](../apps/backend/app/Modules/Exports/Actions/ExportClaimPackage.php) packages structured data, field provenance, and available private receipt files.
- [`ExportAccountData`](../apps/backend/app/Modules/Accounts/Actions/ExportAccountData.php) and [`DeleteAccount`](../apps/backend/app/Modules/Accounts/Actions/DeleteAccount.php) show portability and erasure behavior.

The connected browser proof is [`connected-flow.spec.ts`](../apps/frontend/e2e/connected-flow.spec.ts). It registers/verifies real users, runs the private receipt queue, confirms a deadline, inspects ICS/ZIP downloads, sees a reminder, and proves another household receives 404.

## Minute 6–7: Review decisions and release discipline

Read the short ADRs under [`docs/adr`](adr/), especially:

- [Modular monolith](adr/0001-modular-monolith.md)
- [Framework-agnostic Deadline Engine](adr/0002-framework-agnostic-deadline-engine.md)
- [Local-first processing](adr/0003-local-first-document-processing.md)
- [Human confirmation](adr/0004-human-confirmation-before-deadline-activation.md)
- [Private object storage](adr/0005-private-s3-compatible-storage.md)
- [REST/OpenAPI](adr/0006-rest-openapi-contract.md)
- [Stateful-cookie authentication](adr/0007-sanctum-stateful-cookie-auth.md)

Then scan:

- [Case Study](CASE_STUDY.md), including what failed during connected implementation.
- [Testing Strategy](TESTING.md) for the critical-risk matrix and exact local evidence.
- [Maintainer Notes](MAINTAINER_NOTES.md) for concise interview answers tied to code.
- [Local Performance Baseline](performance/LOCAL_BASELINE.md) for reproducible, non-production measurements.
- [CI workflow](../.github/workflows/ci.yml) and [artifact/SBOM workflow](../.github/workflows/artifacts.yml).

## Commands worth running

```bash
make test
make quality
make e2e
make load
make production-build
```

PowerShell equivalents use `./scripts/kp.ps1 <task>`.

The five highest-signal review areas are:

1. Deadline Engine semantics and boundary tests.
2. Receipt state machine, retry, idempotency, and dispatch reconciliation.
3. Tenant-first route binding plus valid-foreign-ID isolation tests.
4. Human confirmation and optional AI trust boundary.
5. Reminder delivery uncertainty, private exports/deletion, and connected E2E proof.
