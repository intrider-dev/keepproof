# ADR 0007: Use Sanctum Stateful-Cookie Authentication

- Status: Accepted
- Date: 2026-07-20

## Context

KeepProof has a first-party Vue SPA and Laravel API. It needs browser authentication, CSRF protection, email verification, password reset, revocation, and recent-password checks. Storing long-lived bearer tokens in browser storage would increase the impact of script injection without providing a benefit for a same-site first-party client.

## Decision

Use Laravel Sanctum in stateful SPA mode. Nginx serves the Vue application and proxies the API under the same site. The SPA first obtains the CSRF cookie, sends credentials with requests, and supplies the XSRF token header. Authentication state is held in a server-side database session.

Cookie policy is:

- HttpOnly for the session cookie.
- Secure in non-local environments.
- SameSite=Lax.
- Narrow host/domain and path configuration.
- Session ID rotation after login.
- Session invalidation and CSRF token regeneration on logout.

The local HTTP profile may disable Secure only in its documented environment override. CORS uses an exact allowlist and credentialed requests; wildcard origins are forbidden.

Laravel's password hashing, signed email verification, and hashed password-reset tokens are used. Login and recovery endpoints are rate-limited. Export and deletion endpoints require a verified email and a recent password confirmation established by a JSON reauthentication endpoint. Account deletion is synchronous: it removes owned receipt objects first, then deletes database sessions and the user within the relational transaction/cascade path, and finally invalidates the current request session.

The SPA stores no access or refresh token in localStorage or sessionStorage. Personal access tokens and OAuth are outside the first release.

## Consequences

- CSRF is an explicit part of the authentication flow and is covered by tests.
- Session revocation and password confirmation use Laravel's established mechanisms.
- Database sessions let authenticated reads continue when Redis is unavailable.
- The frontend and API must remain same-site or use carefully configured stateful domains and CORS.
- Native/mobile or third-party clients would need a later, separately threat-modeled token mechanism.

## Alternatives rejected

- JWTs in browser storage: unnecessary exposure and custom refresh/revocation complexity.
- A custom OAuth server: outside scope and security-sensitive.
- Redis-only sessions: turns a queue/cache outage into an authentication outage.
- SameSite=Strict: can interfere with signed email and password-reset navigation without a compensating security benefit for this flow.
