# ADR 0005: Use Private S3-Compatible Object Storage

- Status: Accepted
- Date: 2026-07-20

## Context

Receipt files are private evidence and may contain personal data. They must not be placed on a public web filesystem, addressed by user filenames, or exposed through permanent links. Local development also needs a reproducible storage service with an API compatible with common production providers.

MinIO is not selected for the local baseline because its upstream repository/distribution is archived. The application should not depend on vendor-specific storage behavior.

## Decision

Use Laravel Filesystem's S3 adapter against a private bucket. Local Compose pins SeaweedFS 4.39 and enables its S3-compatible gateway. Production may use a supported S3-compatible service after compatibility and durability validation.

Object keys are generated exclusively by the application:

- documents/{shard}/{document-ulid}/{random-value}.{trusted-extension}
- exports/{export-ulid}/{random-value}.zip

Keys and object metadata contain no email, merchant, original filename, or user-controlled path. The original display name is sanitized and stored only in PostgreSQL.

Uploads pass through the backend so it can enforce size, content-derived MIME, pixel/page limits, image re-encoding and metadata removal, PDF structural checks, and SHA-256 calculation. Only JPEG, PNG, and PDF are accepted. Objects are private and quarantined documents are never downloadable.

An authorized download action creates a short-lived signed URL, five minutes by default. Signed URLs are not persisted or logged. Responses use no-store and no-referrer policies, attachment disposition, and MIME nosniff protection.

Database and object storage cannot share a transaction. Uploads use compensating deletion when database persistence fails. Export generation uploads first and marks the request ready only afterward. Account deletion removes and verifies objects before deleting the final relational identity; retries are idempotent. Scheduled cleanup removes confirmed orphaned temporary/export objects.

## Consequences

- Local behavior exercises the same S3 API boundary used in deployment.
- Private-by-default storage and expiring URLs reduce accidental disclosure.
- Signed URL routing must be configured correctly through the local reverse proxy.
- Cross-system operations require explicit compensation, reconciliation, and failure tests.
- Storage migration is a key copy plus checksum verification and database key cutover, not an application rewrite.

## Alternatives rejected

- Laravel public/local disk: unsuitable for private multi-process containers and signed access.
- User-derived object paths: leak metadata and create traversal/collision risks.
- Permanent public links: incompatible with the privacy model.
- MinIO local baseline: avoided because the upstream project distribution is archived.
- Direct browser upload in the MVP: bypasses the single trusted validation boundary.
