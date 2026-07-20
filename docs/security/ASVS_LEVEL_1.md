# OWASP ASVS 5.0.0 Level 1 Tracking

Status: pre-release control tracker

Baseline: [OWASP Application Security Verification Standard 5.0.0](https://owasp.org/www-project-application-security-verification-standard/)

Last reviewed: 2026-07-20

This is a scoped engineering checklist, not an ASVS assessment or certification. It maps every ASVS 5.0.0 Level 1 identifier to the KeepProof architecture. Requirement text is intentionally paraphrased; use the official standard as the authority.

Statuses:

- **Verified**: the control and its cited automated or manual verification passed on the reviewed revision.
- **Implemented**: source evidence exists, but this review has not independently exercised the full control.
- **Open**: required work or evidence is missing.
- **Deployment**: the application exposes the necessary configuration, but the control must be verified at the real ingress/platform.
- **N/A**: the technology or flow is absent from the scoped MVP; the rationale is stated.

Until the final release-candidate audit records a commit SHA and executed commands below, implemented controls must not be represented as verified.

## V1 Encoding and sanitization

| ID           | Status      | KeepProof mapping                                                                                                                                                                                                                                   |
| ------------ | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-1.2.1 | Verified    | `PurchaseApiTest` preserves hostile notes as data and `PurchaseDetailPage.test.ts` proves Vue renders the same payload as text without creating script or event-handler nodes.                                                                      |
| v5.0.0-1.2.2 | Verified    | Server-generated links encode dynamic components; signed receipt URLs use code-owned filenames and bounded TTLs. Receipt and email-link feature tests cover authorization, expiry inputs, and redirects.                                            |
| v5.0.0-1.2.3 | Implemented | JSON responses use framework serialization and no receipt data is assembled into executable scripts.                                                                                                                                                |
| v5.0.0-1.2.4 | Verified    | Database access uses Eloquent/query-builder binding; filter/sort inputs are request-validated and queries remain tenant-scoped. PHPStan max and purchase search tests passed in the recorded gate.                                                  |
| v5.0.0-1.2.5 | Verified    | Tesseract and Poppler are invoked with argument arrays, code-owned executable names, random temporary paths, timeouts, page limits, and output limits. `TesseractDocumentTextExtractorTest` proves hostile content never enters a command argument. |
| v5.0.0-1.3.1 | N/A         | KeepProof accepts plain text, not user-authored HTML or WYSIWYG content. Adding rich text requires a proven sanitizer.                                                                                                                              |
| v5.0.0-1.3.2 | Verified    | Application code does not evaluate receipt content. Prompt-injection and OCR command-boundary tests prove document instructions cannot select tools, execute commands, or activate a deadline. CodeQL provides an additional CI check.              |
| v5.0.0-1.5.1 | N/A         | The MVP exposes no XML input/parser. SVG is not an accepted receipt format.                                                                                                                                                                         |

## V2 Validation and business logic

| ID           | Status      | KeepProof mapping                                                                                                                                                                                                                        |
| ------------ | ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-2.1.1 | Verified    | OpenAPI 3.1 documents the public API, multipart receipt types and 10 MB limit, field bounds, state enums, auth model, and Problem Details errors. Redocly validation and generated-client type checking passed.                          |
| v5.0.0-2.2.1 | Verified    | Dedicated Laravel request objects enforce allowlists, ranges, formats, enum values, and nested item validation. Mass-assignment tests prove submitted tenant, creator, and state fields are ignored.                                     |
| v5.0.0-2.2.2 | Implemented | The backend repeats all security validation; Vue validation is usability-only.                                                                                                                                                           |
| v5.0.0-2.3.1 | Verified    | The document enum and transition action enforce an explicit state graph with an immutable journal. Transition, duplicate-job, retry, and confirmation tests prove out-of-order transitions fail and no deadline activates before review. |

## V3 Web frontend security

| ID           | Status      | KeepProof mapping                                                                                                                                                                                                                                                                                            |
| ------------ | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| v5.0.0-3.2.1 | Verified    | Nginx sets `nosniff` and CSP. ICS, ZIP, and private receipt downloads use explicit content types, attachment dispositions, private/no-store caching where applicable, and server-selected filenames; feature tests assert these boundaries.                                                                  |
| v5.0.0-3.2.2 | Implemented | Vue text interpolation is used for notes and extraction text; raw HTML rendering is prohibited for untrusted content.                                                                                                                                                                                        |
| v5.0.0-3.3.1 | Deployment  | The production Compose override sets a Secure `__Host-keepproof_session` cookie with path `/`, no configured Domain, HttpOnly, and SameSite Lax. The actual `Set-Cookie` header still requires verification behind the chosen HTTPS ingress. Local HTTP intentionally uses development-safe cookie settings. |
| v5.0.0-3.4.1 | Deployment  | HSTS with at least a one-year lifetime must be added and tested at the production TLS ingress; the repository Nginx container does not terminate TLS.                                                                                                                                                        |
| v5.0.0-3.4.2 | Implemented | Credentialed CORS uses an environment-backed exact allowlist; wildcard origins are not configured. Evidence: `apps/backend/config/cors.php`.                                                                                                                                                                 |
| v5.0.0-3.5.1 | Implemented | Sanctum's stateful cookie flow uses CSRF cookie/header validation for state changes.                                                                                                                                                                                                                         |
| v5.0.0-3.5.2 | N/A         | CSRF protection does not rely only on CORS preflight behavior.                                                                                                                                                                                                                                               |
| v5.0.0-3.5.3 | Implemented | Mutations use POST/PATCH/DELETE routes; GET routes must remain side-effect free.                                                                                                                                                                                                                             |

## V4 API and web services

| ID           | Status   | KeepProof mapping                                                                                                                                                           |
| ------------ | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-4.1.1 | Verified | Laravel resources, Problem Details, downloads, and Nginx use explicit media types. API and export tests assert JSON/problem, calendar, ZIP, cache, and disposition headers. |
| v5.0.0-4.4.1 | N/A      | KeepProof does not use WebSockets.                                                                                                                                          |

## V5 File handling

| ID           | Status      | KeepProof mapping                                                                                                                                                                                                                                                                          |
| ------------ | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| v5.0.0-5.2.1 | Verified    | Nginx/PHP cap requests above the 10 MB application limit; images are limited to 25 megapixels and 10,000 pixels per dimension; PDF OCR renders at most three pages; processes and jobs have timeouts; OCR output is capped. Boundary tests passed.                                         |
| v5.0.0-5.2.2 | Verified    | Uploads allow only content-derived JPEG, PNG, and PDF. Images are decoded/re-encoded to remove metadata; signatures are rechecked in the worker; active PDF constructs are quarantined. Spoofed script/SVG, active PDF, checksum, dimensions, and metadata paths are tested.               |
| v5.0.0-5.3.1 | Implemented | Objects are written with private visibility and disclosed only after tenant authorization through a short-lived signed URL with attachment disposition. Fake-storage contract tests passed; anonymous access and bucket policy still require the blocked Docker/provider integration gate. |
| v5.0.0-5.3.2 | Verified    | Storage keys consist only of the authorized household ULID, server-created document ULID, random bytes, and a content-derived extension. Tests prove submitted names and traversal fragments never enter the key.                                                                          |

## V6 Authentication

Documented local API limits are 5 login attempts per minute per normalized email plus IP, 5 registrations per minute per IP, 3 password-reset requests per minute per normalized email plus IP, and 120 authenticated API requests per minute per user. A production ingress may add broader abuse controls but must not create permanent malicious lockout.

| ID           | Status      | KeepProof mapping                                                                                                                                                                                                            |
| ------------ | ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-6.1.1 | Implemented | Rate-limit design and identifiers are documented above and configured in `AppServiceProvider`.                                                                                                                               |
| v5.0.0-6.2.1 | Implemented | Registration/reset currently require at least 12 characters.                                                                                                                                                                 |
| v5.0.0-6.2.2 | Verified    | A verified user can change their password through `PUT /api/v1/me/password`; the operation rotates the remember token, retains the current regenerated session, revokes other database sessions, and records an audit event. |
| v5.0.0-6.2.3 | Verified    | `ChangePassword` checks the current password before its transaction. Feature tests prove a wrong current password leaves the hash and audit log unchanged.                                                                   |
| v5.0.0-6.2.4 | Open        | Add an offline or privacy-reviewed check against at least the applicable top 3,000 compromised passwords.                                                                                                                    |
| v5.0.0-6.2.5 | Open        | The current mixed-case/number/symbol composition rules are stricter than ASVS permits; replace them with length plus compromised-password screening.                                                                         |
| v5.0.0-6.2.6 | Implemented | Authentication forms use password inputs; temporary reveal must remain an explicit user action if offered.                                                                                                                   |
| v5.0.0-6.2.7 | Implemented | Forms do not block paste, browser helpers, or password managers.                                                                                                                                                             |
| v5.0.0-6.2.8 | Implemented | Laravel hashes and verifies the submitted password string without application-level truncation or case conversion.                                                                                                           |
| v5.0.0-6.3.1 | Implemented | Login and password-reset anti-automation limits are enforced server-side and return generic reset responses.                                                                                                                 |
| v5.0.0-6.3.2 | Implemented | There is no privileged default application account. The documented demo user is a regular owner and must be created only in the local demo environment.                                                                      |
| v5.0.0-6.4.1 | Implemented | Reset/invitation secrets use framework CSPRNG facilities, are hashed where stored, expire, and are single-use. Verify each flow with replay tests.                                                                           |
| v5.0.0-6.4.2 | N/A         | Password hints and knowledge-based security questions are not implemented.                                                                                                                                                   |

## V7 Session management

| ID           | Status      | KeepProof mapping                                                                                                                                                                                 |
| ------------ | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-7.2.1 | Implemented | Laravel's backend session guard validates every session; the SPA does not authenticate itself.                                                                                                    |
| v5.0.0-7.2.2 | Implemented | Stateful, dynamically generated reference sessions are used rather than static API keys.                                                                                                          |
| v5.0.0-7.2.3 | Implemented | Session identifiers inherit Laravel's CSPRNG generation. No custom session ID is accepted.                                                                                                        |
| v5.0.0-7.2.4 | Implemented | Login regenerates the session identifier to prevent fixation.                                                                                                                                     |
| v5.0.0-7.4.1 | Implemented | Logout invalidates the server-side session and regenerates the CSRF token.                                                                                                                        |
| v5.0.0-7.4.2 | Verified    | Logout invalidates its session; password change revokes other sessions; account deletion removes all indexed database sessions before the user cascade. Authentication and deletion tests passed. |

## V8 Authorization

| ID           | Status      | KeepProof mapping                                                                                                                                                                                  |
| ------------ | ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-8.1.1 | Verified    | Owner/member permissions, tenant binding, ownership transfer, and recent-password checks are enforced by backend policies/middleware and covered by feature tests.                                 |
| v5.0.0-8.2.1 | Verified    | Laravel policies enforce function-level permissions for households, purchases, and documents; owner-only member mutations are tested.                                                              |
| v5.0.0-8.2.2 | Verified    | Household-scoped route bindings and relationships return the same 404 for foreign, wrongly nested, and missing ULIDs. Backend and connected Playwright suites contain tenant-isolation assertions. |
| v5.0.0-8.3.1 | Implemented | Authorization is enforced in Laravel; frontend visibility is not a security control.                                                                                                               |

## V9 Self-contained tokens

| ID           | Status | KeepProof mapping                                                                                               |
| ------------ | ------ | --------------------------------------------------------------------------------------------------------------- |
| v5.0.0-9.1.1 | N/A    | Authentication uses server-side reference sessions, not JWT or another self-contained bearer token.             |
| v5.0.0-9.1.2 | N/A    | No self-contained authentication token or caller-selected algorithm is accepted.                                |
| v5.0.0-9.1.3 | N/A    | No token header can select a validation key source.                                                             |
| v5.0.0-9.2.1 | N/A    | No self-contained authentication token is used; signed temporary application links have separate expiry checks. |

## V10 OAuth and OpenID Connect

| ID            | Status | KeepProof mapping                               |
| ------------- | ------ | ----------------------------------------------- |
| v5.0.0-10.4.1 | N/A    | KeepProof is not an OAuth authorization server. |
| v5.0.0-10.4.2 | N/A    | No authorization-code flow exists.              |
| v5.0.0-10.4.3 | N/A    | No authorization-code flow exists.              |
| v5.0.0-10.4.4 | N/A    | No OAuth grants are exposed.                    |
| v5.0.0-10.4.5 | N/A    | No refresh tokens are issued.                   |

## V11 Cryptography

| ID            | Status      | KeepProof mapping                                                                                                                                                              |
| ------------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| v5.0.0-11.3.1 | Implemented | Application code does not implement custom block encryption or RSA padding; framework/platform primitives are required. Dependency and configuration review remains necessary. |
| v5.0.0-11.3.2 | Implemented | Any configured application encryption must use Laravel-supported authenticated encryption; no caller-selectable cipher is exposed.                                             |
| v5.0.0-11.4.1 | Implemented | Passwords, invitation tokens, and signatures use framework hashing/MAC APIs; non-cryptographic hashes are not used as security controls.                                       |

## V12 Secure communication

| ID            | Status     | KeepProof mapping                                                                                                                     |
| ------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-12.1.1 | Deployment | Production ingress must allow TLS 1.2/1.3 only and prefer TLS 1.3; local HTTP Compose is explicitly outside this control.             |
| v5.0.0-12.2.1 | Deployment | All public and provider connections must use TLS. Internal plaintext traffic requires a private trusted network or service-level TLS. |
| v5.0.0-12.2.2 | Deployment | Public endpoints require a currently valid certificate chaining to a trusted public CA.                                               |

## V13 Configuration

| ID            | Status      | KeepProof mapping                                                                                                                                                                            |
| ------------- | ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-13.4.1 | Implemented | Production images copy explicit application/build directories rather than the repository root metadata; Nginx serves only frontend assets and Laravel `public`. Verify image contents in CI. |

## V14 Data protection

| ID            | Status   | KeepProof mapping                                                                                                                                                                                                                                                                                                                           |
| ------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-14.2.1 | Open     | Password-reset links currently place the short-lived token and email in the URL query. Referrer policy reduces exposure but does not satisfy this requirement; redesign as a one-time code exchange before claiming compliance.                                                                                                             |
| v5.0.0-14.3.1 | Verified | Successful connected logout and demo logout clear identity, purchases, documents, members, notifications, reminders, preferences, and transient toast state from the SPA store. The store regression test passed. A failed network logout remains visibly unsuccessful because an HttpOnly server session cannot be revoked by client code. |

## V15 Secure coding and architecture

Dependency remediation targets for reachable vulnerabilities are: block release for critical findings; 7 days for high; 30 days for medium; and 90 days for low, unless a documented risk acceptance with an owner and review date exists. An unavailable fix requires mitigating or removing the affected path, not silently suppressing the audit.

| ID            | Status      | KeepProof mapping                                                                                                                                                                                                                             |
| ------------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| v5.0.0-15.1.1 | Implemented | The risk-based update targets above, weekly Dependabot, and CI audit severity policies define dependency maintenance.                                                                                                                         |
| v5.0.0-15.2.1 | Verified    | Backend and Deadline Engine Composer audits and `npm audit --audit-level=high` returned no advisories in the local evidence run. Frontend `quality` now includes its audit; dependency-review blocks newly introduced high/critical findings. |
| v5.0.0-15.3.1 | Implemented | API resources expose explicit field subsets; models are not serialized directly across the API boundary.                                                                                                                                      |

## Verification record

Local release-candidate evidence was recorded on 2026-07-20 after a clean KeepProof volume reset and setup:

- Backend quality: 92 tests and 697 assertions passed. Composer validation and audit, Pint across 182 files, PHPStan/Larastan, Compose origin contracts, and the real Tesseract PNG/PDF contracts inside the backend image passed.
- Deadline Engine: 34 tests and 46,276 assertions passed; Composer validation, PHPStan, and audit passed.
- Frontend: 7 test files and 22 tests passed; Prettier, ESLint, strict TypeScript, OpenAPI lint, generated-client drift detection, production build, and `npm audit --audit-level=high` passed with zero reported high-severity vulnerabilities.
- Connected Playwright: 10/10 tests passed, including two-account tenant isolation, verified registration, public-origin session rotation/revocation, private object storage, Redis-backed processing, review/confirmation, reminder display, ICS/ZIP inspection, accessibility smoke tests, and screenshot generation.
- Production images built successfully. The non-root Nginx configuration passed `nginx -t`, an ephemeral production-ingress smoke request returned HTTP 200, and anonymous private-storage access remained denied.
- Reminder, partial deletion, tenant isolation, upload, stored-XSS, mass-assignment, prompt-injection, signed-URL, export, password/session, and log-redaction tests are part of the suites above.

This is local implementation evidence, not ASVS certification or a penetration test. TLS, certificates, HSTS verification at the deployment edge, production bucket/IAM policy, a monitored restore drill, malware controls, and jurisdiction-specific privacy review remain deployment work.
