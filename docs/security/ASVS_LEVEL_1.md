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

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-1.2.1 | Implemented | Vue interpolation renders untrusted notes/OCR as text; Laravel resources serialize API output. Add focused stored-XSS tests before marking verified. |
| v5.0.0-1.2.2 | Implemented | Server-generated links encode dynamic components; client navigation uses router APIs. Review every signed/download URL construction. |
| v5.0.0-1.2.3 | Implemented | JSON responses use framework serialization and no receipt data is assembled into executable scripts. |
| v5.0.0-1.2.4 | Implemented | Database access uses Eloquent/query-builder parameter binding; allowlist any dynamic sort fields. Larastan and feature tests are CI gates. |
| v5.0.0-1.2.5 | Open | The planned OCR adapter must use code-owned executables and argument arrays without shell interpolation; hostile filename/content tests are required evidence. |
| v5.0.0-1.3.1 | N/A | KeepProof accepts plain text, not user-authored HTML or WYSIWYG content. Adding rich text requires a proven sanitizer. |
| v5.0.0-1.3.2 | Implemented | Application code does not use `eval` or dynamic execution of receipt content; CodeQL covers JavaScript/TypeScript. |
| v5.0.0-1.5.1 | N/A | The MVP exposes no XML input/parser. SVG is not an accepted receipt format. |

## V2 Validation and business logic

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-2.1.1 | Open | Request objects document existing account/household inputs, but the complete OpenAPI schemas and receipt-processing limits are not yet present. |
| v5.0.0-2.2.1 | Implemented | Laravel request objects perform allowlist/range/format validation at API boundaries. |
| v5.0.0-2.2.2 | Implemented | The backend repeats all security validation; Vue validation is usability-only. |
| v5.0.0-2.3.1 | Open | Implement controlled document-state transitions and confirmation-before-activation, then prove skipped/out-of-order transitions fail. |

## V3 Web frontend security

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-3.2.1 | Open | `nosniff` and production CSP exist, but receipt/export download routes and their attachment/sandbox behavior are not yet implemented and verified. |
| v5.0.0-3.2.2 | Implemented | Vue text interpolation is used for notes and extraction text; raw HTML rendering is prohibited for untrusted content. |
| v5.0.0-3.3.1 | Open | Production must use HTTPS-only cookies with a `__Host-` or `__Secure-` name and compatible path/domain settings. Local HTTP intentionally cannot satisfy this. |
| v5.0.0-3.4.1 | Deployment | HSTS with at least a one-year lifetime must be added and tested at the production TLS ingress; the repository Nginx container does not terminate TLS. |
| v5.0.0-3.4.2 | Implemented | Credentialed CORS uses an environment-backed exact allowlist; wildcard origins are not configured. Evidence: `apps/backend/config/cors.php`. |
| v5.0.0-3.5.1 | Implemented | Sanctum's stateful cookie flow uses CSRF cookie/header validation for state changes. |
| v5.0.0-3.5.2 | N/A | CSRF protection does not rely only on CORS preflight behavior. |
| v5.0.0-3.5.3 | Implemented | Mutations use POST/PATCH/DELETE routes; GET routes must remain side-effect free. |

## V4 API and web services

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-4.1.1 | Implemented | Laravel responses, Problem Details, downloads, and Nginx serve explicit media types. Add response-header contract assertions for exports. |
| v5.0.0-4.4.1 | N/A | KeepProof does not use WebSockets. |

## V5 File handling

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-5.2.1 | Open | The reverse-proxy body limit exists; add matching API limits plus bounded PDF/OCR resource handling and tests. |
| v5.0.0-5.2.2 | Open | Implement JPG/PNG/PDF allowlisting with content-derived MIME/signature checks, image rewriting, PDF validation, and hostile-file tests. |
| v5.0.0-5.3.1 | Open | Private S3-compatible infrastructure is configured, but authorized upload/download behavior and access tests are not yet implemented. |
| v5.0.0-5.3.2 | Open | Receipt storage code must prove keys come from internal identifiers rather than submitted filenames or paths. |

## V6 Authentication

Documented local API limits are 5 login attempts per minute per normalized email plus IP, 5 registrations per minute per IP, 3 password-reset requests per minute per normalized email plus IP, and 120 authenticated API requests per minute per user. A production ingress may add broader abuse controls but must not create permanent malicious lockout.

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-6.1.1 | Implemented | Rate-limit design and identifiers are documented above and configured in `AppServiceProvider`. |
| v5.0.0-6.2.1 | Implemented | Registration/reset currently require at least 12 characters. |
| v5.0.0-6.2.2 | Open | Authenticated password change is not yet present. Password reset alone does not satisfy this requirement. |
| v5.0.0-6.2.3 | Open | Add password change with current-password verification and tests. |
| v5.0.0-6.2.4 | Open | Add an offline or privacy-reviewed check against at least the applicable top 3,000 compromised passwords. |
| v5.0.0-6.2.5 | Open | The current mixed-case/number/symbol composition rules are stricter than ASVS permits; replace them with length plus compromised-password screening. |
| v5.0.0-6.2.6 | Implemented | Authentication forms use password inputs; temporary reveal must remain an explicit user action if offered. |
| v5.0.0-6.2.7 | Implemented | Forms do not block paste, browser helpers, or password managers. |
| v5.0.0-6.2.8 | Implemented | Laravel hashes and verifies the submitted password string without application-level truncation or case conversion. |
| v5.0.0-6.3.1 | Implemented | Login and password-reset anti-automation limits are enforced server-side and return generic reset responses. |
| v5.0.0-6.3.2 | Implemented | There is no privileged default application account. The documented demo user is a regular owner and must be created only in the local demo environment. |
| v5.0.0-6.4.1 | Implemented | Reset/invitation secrets use framework CSPRNG facilities, are hashed where stored, expire, and are single-use. Verify each flow with replay tests. |
| v5.0.0-6.4.2 | N/A | Password hints and knowledge-based security questions are not implemented. |

## V7 Session management

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-7.2.1 | Implemented | Laravel's backend session guard validates every session; the SPA does not authenticate itself. |
| v5.0.0-7.2.2 | Implemented | Stateful, dynamically generated reference sessions are used rather than static API keys. |
| v5.0.0-7.2.3 | Implemented | Session identifiers inherit Laravel's CSPRNG generation. No custom session ID is accepted. |
| v5.0.0-7.2.4 | Implemented | Login regenerates the session identifier to prevent fixation. |
| v5.0.0-7.4.1 | Implemented | Logout invalidates the server-side session and regenerates the CSRF token. |
| v5.0.0-7.4.2 | Open | Account deletion and removal of every database session are not yet implemented in the backend. |

## V8 Authorization

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-8.1.1 | Implemented | Owner/member permissions, tenant binding, and sensitive-operation re-authentication are documented in architecture/security docs. |
| v5.0.0-8.2.1 | Implemented | Laravel policies enforce function-level permissions. |
| v5.0.0-8.2.2 | Implemented | Household-scoped bindings and relationships hide foreign ULIDs and prevent BOLA/IDOR. |
| v5.0.0-8.3.1 | Implemented | Authorization is enforced in Laravel; frontend visibility is not a security control. |

## V9 Self-contained tokens

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-9.1.1 | N/A | Authentication uses server-side reference sessions, not JWT or another self-contained bearer token. |
| v5.0.0-9.1.2 | N/A | No self-contained authentication token or caller-selected algorithm is accepted. |
| v5.0.0-9.1.3 | N/A | No token header can select a validation key source. |
| v5.0.0-9.2.1 | N/A | No self-contained authentication token is used; signed temporary application links have separate expiry checks. |

## V10 OAuth and OpenID Connect

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-10.4.1 | N/A | KeepProof is not an OAuth authorization server. |
| v5.0.0-10.4.2 | N/A | No authorization-code flow exists. |
| v5.0.0-10.4.3 | N/A | No authorization-code flow exists. |
| v5.0.0-10.4.4 | N/A | No OAuth grants are exposed. |
| v5.0.0-10.4.5 | N/A | No refresh tokens are issued. |

## V11 Cryptography

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-11.3.1 | Implemented | Application code does not implement custom block encryption or RSA padding; framework/platform primitives are required. Dependency and configuration review remains necessary. |
| v5.0.0-11.3.2 | Implemented | Any configured application encryption must use Laravel-supported authenticated encryption; no caller-selectable cipher is exposed. |
| v5.0.0-11.4.1 | Implemented | Passwords, invitation tokens, and signatures use framework hashing/MAC APIs; non-cryptographic hashes are not used as security controls. |

## V12 Secure communication

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-12.1.1 | Deployment | Production ingress must allow TLS 1.2/1.3 only and prefer TLS 1.3; local HTTP Compose is explicitly outside this control. |
| v5.0.0-12.2.1 | Deployment | All public and provider connections must use TLS. Internal plaintext traffic requires a private trusted network or service-level TLS. |
| v5.0.0-12.2.2 | Deployment | Public endpoints require a currently valid certificate chaining to a trusted public CA. |

## V13 Configuration

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-13.4.1 | Implemented | Production images copy explicit application/build directories rather than the repository root metadata; Nginx serves only frontend assets and Laravel `public`. Verify image contents in CI. |

## V14 Data protection

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-14.2.1 | Open | Password-reset links currently place the short-lived token and email in the URL query. Referrer policy reduces exposure but does not satisfy this requirement; redesign as a one-time code exchange before claiming compliance. |
| v5.0.0-14.3.1 | Open | The backend invalidates logout sessions, but the SPA demo store retains user-scoped in-memory data. Clear client state and add online/offline logout tests. |

## V15 Secure coding and architecture

Dependency remediation targets for reachable vulnerabilities are: block release for critical findings; 7 days for high; 30 days for medium; and 90 days for low, unless a documented risk acceptance with an owner and review date exists. An unavailable fix requires mitigating or removing the affected path, not silently suppressing the audit.

| ID | Status | KeepProof mapping |
| --- | --- | --- |
| v5.0.0-15.1.1 | Implemented | The risk-based update targets above, weekly Dependabot, and CI audit severity policies define dependency maintenance. |
| v5.0.0-15.2.1 | Open | Composer and npm audits must pass on the release-candidate revision; record results below. Dependency-review blocks newly introduced high/critical findings. |
| v5.0.0-15.3.1 | Implemented | API resources expose explicit field subsets; models are not serialized directly across the API boundary. |

## Verification record

No release-candidate revision has been recorded yet. Before a release, replace this paragraph with:

- commit SHA and UTC date;
- `make quality`, `make test`, and `make e2e` results;
- `composer audit --locked` for both PHP projects and `npm audit --audit-level=high` results;
- focused tenant-isolation, upload, stored-XSS, signed-URL, export, deletion, and session tests;
- production ingress evidence for TLS, HSTS, secure cookie naming, CORS, CSP, and request-size behavior; and
- links to accepted risks for every remaining **Open** item.
