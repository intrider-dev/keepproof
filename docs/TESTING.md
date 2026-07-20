# Testing strategy

KeepProof tests behavior according to product and security risk. A coverage percentage is not used as a release claim: line coverage cannot prove tenant isolation, transactional rollback, private-object cleanup, or at-least-once job safety. The release gate instead maps every critical MVP boundary to focused tests and then exercises the complete browser journey against the real local infrastructure.

## Verified snapshot

The following results were recorded locally on 2026-07-20:

- The current constituent quality gates passed: 92 backend tests with 697 assertions, 34 Deadline Engine tests with 46,276 assertions, and 23 frontend unit/component tests in 8 files, plus format, static-analysis, build, contract-drift, and dependency-audit checks.
- The full Docker Playwright suite passed all 12 scenarios. The Playwright run took 40.9 seconds and the complete wrapper, including environment reconfiguration and restoration, took 115.3 seconds.
- The connected browser flow used PostgreSQL, Redis, private S3-compatible storage, Mailpit, the queue worker, scheduler, and Nginx. It did not replace those dependencies with mocks.
- The real local Tesseract contract processed synthetic PNG and PDF fixtures inside the backend image.
- Composer and npm audits reported no advisories at their configured release thresholds.

These are local implementation results, not remote CI results, a penetration test, or a production reliability claim.

## Critical-risk matrix

| Risk or user outcome                                                                                                                                                                               | Primary evidence                                                                                                                                                                                                                                                                                                         |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Registration creates exactly one user, personal household, owner membership, and audit record                                                                                                      | [`AuthenticationApiTest.php`](../apps/backend/tests/Feature/Accounts/AuthenticationApiTest.php) and the connected registration flow in [`connected-flow.spec.ts`](../apps/frontend/e2e/connected-flow.spec.ts)                                                                                                           |
| Anonymous authentication and recovery mutations are CSRF-protected and fail closed for an untrusted origin; login rotates the session, rate-limits failures, and works on a configured custom port | [`AuthenticationApiTest.php`](../apps/backend/tests/Feature/Accounts/AuthenticationApiTest.php), Compose contracts in [`assert-compose-config.mjs`](../tests/infra/assert-compose-config.mjs), and the public-origin browser regression in [`connected-flow.spec.ts`](../apps/frontend/e2e/connected-flow.spec.ts)       |
| Email verification, reset tokens, recent-password confirmation, password change, and logout cannot be bypassed                                                                                     | [`AuthenticationApiTest.php`](../apps/backend/tests/Feature/Accounts/AuthenticationApiTest.php)                                                                                                                                                                                                                          |
| Household lists and direct identifiers never expose another tenant; only owners administer members                                                                                                 | [`HouseholdAuthorizationTest.php`](../apps/backend/tests/Feature/Households/HouseholdAuthorizationTest.php) and the connected two-account denial scenario                                                                                                                                                                |
| Purchase create, read, update, delete, filters, pagination, integer money, mass-assignment boundaries, and nested binding behave consistently                                                      | [`PurchaseApiTest.php`](../apps/backend/tests/Feature/Purchases/PurchaseApiTest.php)                                                                                                                                                                                                                                     |
| A late write cannot leave partially updated purchase items, deadlines, extraction fields, reminders, transitions, or idempotency records                                                           | Trigger-backed rollback cases in [`PurchaseApiTest.php`](../apps/backend/tests/Feature/Purchases/PurchaseApiTest.php), [`ReceiptWorkflowTest.php`](../apps/backend/tests/Feature/Documents/ReceiptWorkflowTest.php), and [`AuthenticationApiTest.php`](../apps/backend/tests/Feature/Accounts/AuthenticationApiTest.php) |
| Uploads use content-derived MIME checks, opaque private keys, image re-encoding, bounded files, idempotency, and quarantine unsafe PDFs/formats                                                    | [`ReceiptWorkflowTest.php`](../apps/backend/tests/Feature/Documents/ReceiptWorkflowTest.php) and [`TesseractReceiptContractTest.php`](../apps/backend/tests/Feature/Documents/TesseractReceiptContractTest.php)                                                                                                          |
| Receipt processing transitions are controlled; retries are bounded, idempotent, overlap-safe, and recoverable after a queue dispatch outage                                                        | [`ReceiptWorkflowTest.php`](../apps/backend/tests/Feature/Documents/ReceiptWorkflowTest.php)                                                                                                                                                                                                                             |
| OCR and optional AI treat receipt text as data, not instructions; external processing needs two gates, redaction, schema validation, and budgets                                                   | Document unit contracts in [`tests/Unit/Documents`](../apps/backend/tests/Unit/Documents/) including the prompt-injection fixture                                                                                                                                                                                        |
| No deadline activates until human confirmation; confirmed values remain separate from encrypted raw OCR data                                                                                       | [`ReceiptWorkflowTest.php`](../apps/backend/tests/Feature/Documents/ReceiptWorkflowTest.php) and the connected review flow                                                                                                                                                                                               |
| Return and warranty rules explain month-end, leap-year, inclusive/exclusive, override, and timezone behavior                                                                                       | [`packages/deadline-engine/tests`](../packages/deadline-engine/tests/)                                                                                                                                                                                                                                                   |
| Dashboard results, reminders, quiet hours, in-app access, retry exhaustion, and ambiguous email delivery stay tenant-scoped and deduplicated                                                       | [`DashboardApiTest.php`](../apps/backend/tests/Feature/Dashboard/DashboardApiTest.php) and [`ReminderWorkflowTest.php`](../apps/backend/tests/Feature/Notifications/ReminderWorkflowTest.php)                                                                                                                            |
| Reactive notification preferences render safely, primary SPA destinations never leave an empty router view, and the brand link recovers dashboard navigation                                       | [`NotificationSettingsPage.test.ts`](../apps/frontend/src/pages/NotificationSettingsPage.test.ts) and the desktop/mobile navigation regression in [`demo-flow.spec.ts`](../apps/frontend/e2e/demo-flow.spec.ts)                                                                                                          |
| ICS, claim ZIP, account ZIP, original deletion, and account erasure are authorized, audited, portable, and storage-aware                                                                           | [`ExportAndPrivacyTest.php`](../apps/backend/tests/Feature/Exports/ExportAndPrivacyTest.php), [`ReceiptWorkflowTest.php`](../apps/backend/tests/Feature/Documents/ReceiptWorkflowTest.php), and artifact inspection in the connected E2E                                                                                 |
| Stored notes cannot become executable markup and API failures remain Problem Details                                                                                                               | [`PurchaseDetailPage.test.ts`](../apps/frontend/src/pages/PurchaseDetailPage.test.ts), [`PurchaseApiTest.php`](../apps/backend/tests/Feature/Purchases/PurchaseApiTest.php), and [`api.test.ts`](../apps/frontend/src/lib/api.test.ts)                                                                                   |
| Every versioned route appears in OpenAPI and generated frontend types cannot silently drift                                                                                                        | [`OpenApiContractTest.php`](../apps/backend/tests/Feature/Shared/OpenApiContractTest.php) plus `lint:api` and `check:api` in [`package.json`](../apps/frontend/package.json)                                                                                                                                             |
| Service health fails closed when private storage is not prepared                                                                                                                                   | [`HealthEndpointTest.php`](../apps/backend/tests/Feature/Shared/HealthEndpointTest.php) and [`PreparePrivateStorageTest.php`](../apps/backend/tests/Feature/Shared/PreparePrivateStorageTest.php)                                                                                                                        |
| The main flow is keyboard/render accessible at desktop and mobile sizes                                                                                                                            | [`demo-flow.spec.ts`](../apps/frontend/e2e/demo-flow.spec.ts) includes axe smoke checks; component and validation tests cover focused UI behavior                                                                                                                                                                        |

## Test realism

Backend feature tests use deterministic factories and an in-memory relational database for fast transaction and failure-injection checks. They fake only the boundary under examination, such as a queue dispatch or an unavailable object store. The connected browser test then catches environment-specific differences by using the actual PostgreSQL schema, Redis transport, private object storage, SMTP capture, worker, scheduler, and reverse proxy.

Failure cases intentionally generate error or warning log lines. A passing rollback test must observe both the safe Problem Details response and the absence of partial authoritative state; suppressing the log would hide useful evidence.

Synthetic receipts under [`tests/fixtures`](../tests/fixtures/) contain no real personal data. Unique E2E accounts use the reserved `.test` domain and Mailpit never delivers externally.

## Commands

From Linux or macOS:

```bash
make test
make quality
make e2e
```

From Windows PowerShell:

```powershell
./scripts/kp.ps1 test
./scripts/kp.ps1 quality
./scripts/kp.ps1 e2e
```

The test, quality, and E2E entry points first validate both development and production Compose origin contracts. The E2E wrapper temporarily uses the internal Nginx origin and restores the configured public origin even if a browser assertion fails.

## Deliberate limits

- No coverage percentage is published because no instrumented coverage run was made.
- Automated accessibility checks are a smoke layer, not a substitute for a full manual WCAG 2.2 AA audit with multiple assistive technologies.
- The optional paid LLM path is contract-tested with deterministic HTTP responses and remains disabled in CI.
- Browser E2E runs Chromium desktop and mobile viewports; cross-browser certification is not claimed.
- External SMTP delivery, cloud IAM, TLS termination, monitored backup restore, and deployment-specific malware scanning require tests in the target environment.
