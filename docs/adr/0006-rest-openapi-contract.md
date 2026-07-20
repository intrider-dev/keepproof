# ADR 0006: Use a Versioned REST API and OpenAPI Contract

- Status: Accepted
- Date: 2026-07-20

## Context

The Vue SPA is a separate application and must not depend on Laravel views or undocumented response shapes. The repository also needs an API that can be understood by reviewers and used later by other clients without introducing multiple competing API styles.

## Decision

Expose JSON resources under /api/v1. Tenant-owned resources use explicit household-scoped paths and Laravel scoped bindings, followed by policy checks. Controllers remain thin; dedicated request objects validate input, verb-named actions execute use cases, and API resources serialize responses.

OpenAPI 3.1 is the public contract. The tracked specification defines operations, schemas, authentication behavior, pagination, idempotency headers, and error responses. The Vue TypeScript client is generated from that specification and committed or generated deterministically in CI. Contract tests compare representative Laravel responses with the specification.

Errors use RFC 9457 Problem Details with application/problem+json. Validation problems add a structured errors member. Responses include a correlation identifier but never sensitive receipt content.

Collections use bounded pagination and explicit filters. Critical replayable operations, including upload, extraction confirmation, export creation, and deletion request, require an Idempotency-Key. Reusing the key with a different request returns a conflict.

Local API documentation renders the same tracked specification. Breaking changes require a new API version; additive compatible changes stay within v1.

## Consequences

- Backend and frontend drift is caught by generation, type checking, and contract tests.
- Reviewers can inspect and exercise the API without reading controllers.
- The specification must change in the same pull request as behavior.
- Explicit tenant paths are more verbose but make authorization context visible.
- Versioning does not remove the need for a documented deprecation policy before a public deployment.

## Alternatives rejected

- Inertia or server-rendered pages: would weaken the explicit public API boundary requested for the project.
- GraphQL alongside REST: duplicates contracts and operational surface without an MVP need.
- Controller-derived undocumented JSON: invites frontend drift.
- Runtime reflection as the sole OpenAPI source: convenient annotations can still miss business and authorization semantics.
