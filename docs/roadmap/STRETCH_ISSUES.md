# Stretch Issue Drafts

No GitHub remote is configured in this checkout, so these scopes are tracked as issue-ready drafts rather than pretending remote issues were created. Publish them after the repository has an origin. Stretch work starts only after the MVP release-candidate gates remain green.

## 1. `feat(example): add a Symfony Deadline Engine integration`

Labels: `enhancement`, `example`, `php`

### User/developer problem

The Deadline Engine is framework-agnostic, but a reviewer should be able to see a real Symfony dependency-injection boundary without copying the Laravel application or adding a second product runtime.

### Scope

- Create `examples/symfony` against the supported Symfony release selected at implementation time.
- Require `keepproof/deadline-engine` through a Composer path repository.
- Register `DeadlineCalculator` with Symfony DI configuration.
- Expose one console command or small JSON controller that calculates return and warranty deadlines from a committed synthetic input fixture.
- Include focused integration tests and an independent README.

### Acceptance criteria

- `composer validate`, static analysis, and tests pass independently.
- Example output includes source, rule summary, and trace—not only a date.
- No database, authentication, OCR, or copied KeepProof module code is added.
- Root quality documentation includes the exact command.

### Non-goals

Rebuilding KeepProof in Symfony, benchmarking frameworks, or introducing a runtime service.

## 2. `feat(example): add a Yii3 Deadline Engine integration`

Labels: `enhancement`, `example`, `php`

### User/developer problem

The package should demonstrate compatibility with Yii3's package ecosystem and DI configuration while keeping one authoritative implementation of deadline rules.

### Scope

- Create `examples/yii` from the maintained Yii3 application template selected at implementation time.
- Require the local Deadline Engine package through Composer.
- Configure `DeadlineCalculator` in the Yii container.
- Add one command or handler that maps a synthetic request DTO to the package and serializes its explanation trace.
- Add tests and a concise README.

### Acceptance criteria

- Dependency installation, static analysis, and tests pass on PHP 8.5.
- The example uses Yii DI rather than `new` inside the delivery handler.
- No Laravel models, database schema, frontend, or copied domain classes appear.
- Dependabot/CI covers the example if it becomes tracked runtime code.

### Non-goals

A second KeepProof backend or framework comparison marketing.

## 3. `research(extraction): evaluate an isolated FastAPI OCR worker`

Labels: `research`, `performance`, `privacy`

### Problem

Tesseract currently runs safely in Laravel queue workers. A Python service would add authentication, network, deployment, tracing, and privacy paths; it should exist only if measurements show a useful resource/runtime isolation boundary.

### Scope

- Profile CPU, memory, queue age, and processing duration for representative synthetic PNG/JPG/PDF batches.
- Define a decision threshold for worker isolation before implementation.
- If justified, prototype a stateless FastAPI endpoint with bounded payload/object reference, authentication, timeout, correlation ID, no persistence/logged text, and the existing extraction contract.
- Compare failure recovery and local setup cost with separate Laravel worker pools.

### Acceptance criteria

- The issue records reproducible measurements and a keep/extract decision.
- No service is merged if separate worker pools solve the measured problem.
- Any accepted service has contract/security tests and cannot activate purchases or deadlines.
- Threat model, Compose, operations, and deletion/data-flow documentation are updated.

### Non-goals

Adding Python for portfolio breadth or moving purchase truth outside Laravel.

## 4. `feat(exports): add an expiring read-only claim share link`

Labels: `enhancement`, `exports`, `security`

### User problem

A user may need to provide a repair shop or retailer with a time-limited proof package without creating a household account or publishing a permanent object URL.

### Scope

- Owner/member explicitly creates a claim share for one purchase after recent password confirmation.
- Store only a hash of a cryptographically random token, purchase/household scope, expiry, revocation, creator, and audit metadata.
- Serve a minimal read-only claim view/package through a rate-limited, no-store, no-referrer route.
- Never expose raw storage keys; generate receipt access only after validating the share on each request.
- Allow listing and immediate revocation from privacy settings.

### Acceptance criteria

- Default TTL is short and bounded; expired/revoked/foreign tokens return non-disclosing 404.
- Token is shown once, absent from logs and analytics, and protected from indexing/referrer leakage.
- Tests cover brute-force throttling, expiry, revocation, tenant scope, receipt deletion, and account deletion.
- Threat model and account export include share metadata.

### Non-goals

Public profiles, editable shares, legal representations, or permanent links.

## 5. `feat(integrations): add signed outbound webhooks`

Labels: `enhancement`, `integrations`, `security`

### User/developer problem

Advanced self-hosters may need a machine-readable signal when a document requires review or a confirmed deadline changes.

### Scope

- Household owner configures HTTPS endpoints and receives a one-time signing secret.
- Allowlist a small versioned event set: `document.needs_review`, `purchase.deadlines_changed`, and `reminder.delivery_unknown`.
- Queue delivery with event ID, timestamp, HMAC signature, timeout, bounded retry/backoff, and a delivery journal.
- Protect against SSRF, DNS rebinding, internal addresses, oversized responses, replay, and secret leakage.

### Acceptance criteria

- Signature verification example and replay window are documented.
- Payload contains IDs and minimal structured data, never raw OCR text, receipt files, signed URLs, or notes by default.
- Tests cover signature, secret rotation, duplicate event ID, retry exhaustion, endpoint disablement, and account deletion.
- A complete transactional delivery-intent design is implemented; no decorative outbox table.

### Non-goals

Inbound webhooks, arbitrary event scripting, or an automation platform.

## 6. `feat(i18n): add extracted message catalogs and a second locale`

Labels: `enhancement`, `frontend`, `accessibility`

### User problem

KeepProof stores an account locale but currently ships only English UI text. Adding languages safely requires a real catalog boundary rather than scattered conditional strings.

### Scope

- Introduce a small maintained Vue i18n solution after dependency review, or a typed in-repo catalog if it remains simpler.
- Extract UI and validation copy into typed English keys.
- Add Russian as the second complete catalog without changing English default positioning.
- Drive date/number/currency formatting from the authenticated locale and timezone.
- Update `<html lang>`, accessible names, email templates, and fallback behavior.

### Acceptance criteria

- No missing keys in build/test; English fallback is deterministic.
- Critical Playwright path runs in both locales on desktop and mobile.
- Long text, focus order, errors, and accessible names remain usable.
- OpenAPI continues to constrain supported locale values and backend notifications use the same preference.

### Non-goals

Machine-translated partial UI, locale-based law advice, or changing stored currency.

## 7. `feat(notifications): add opt-in Web Push reminders`

Labels: `enhancement`, `notifications`, `privacy`

### User problem

Some users need deadline reminders without relying on email, but browser push requires explicit permission, revocable subscriptions, and careful payload minimization.

### Scope

- Add a service worker and user-initiated permission/subscription flow.
- Store encrypted endpoint/auth material with user scope and last-success/failure metadata.
- Add `web_push` as a reminder channel with the existing deduplication identity and quiet-hour semantics.
- Use minimal notification text and route authenticated clicks to the relevant purchase; no receipt text or merchant name on locked-screen payloads by default.
- Remove invalid subscriptions and include them in account export/deletion.

### Acceptance criteria

- VAPID secrets are environment-only and rotation is documented.
- Permission denial leaves in-app/email behavior unchanged.
- Tests cover duplicate jobs, expired subscriptions, provider timeout, payload privacy, logout/account deletion, and offline click behavior.
- Browser support and production HTTPS requirement are documented.

### Non-goals

Silent permission prompts, advertising notifications, or third-party analytics SDKs.
