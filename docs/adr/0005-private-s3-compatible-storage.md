# ADR 0005: Use Private S3-Compatible Object Storage

- Status: Accepted
- Date: 2026-07-20

## Context

Receipt files are private evidence and may contain personal data. They must not be placed on a public web filesystem, addressed by user filenames, or exposed through permanent links. Local development also needs a reproducible storage service with an API compatible with common production providers.

MinIO is not selected for the local baseline because its upstream repository/distribution is archived. The application should not depend on vendor-specific storage behavior.

## Decision

Use Laravel Filesystem's S3 adapter against a private bucket. Local Compose pins SeaweedFS 4.39 and enables its S3-compatible gateway. Production may use a supported S3-compatible service after compatibility and durability validation.

Object keys are generated exclusively by the application:

- households/{household-ulid}/receipts/{document-ulid}/{random-value}.{trusted-extension}

Keys and object metadata contain no email, merchant, original filename, or user-controlled path. Household/document ULIDs provide application scope without personal labels. The original display name is sanitized and stored only in PostgreSQL.

Uploads pass through the backend so it can enforce a 10 MiB limit, content-derived MIME, image dimensions/pixel count, image re-encoding and metadata removal, and SHA-256 calculation. Only JPEG, PNG, and PDF are accepted. The worker rechecks the checksum and content signature, rejects known active/embedded PDF markers, and limits local PDF OCR to the first three pages. The current upload path does not reject a PDF solely because it has more than three pages. Objects are private and quarantined documents are never downloadable.

An authorized POST download action creates a short-lived signed URL, five minutes by default and bounded to 1–15 minutes. Signed URLs are not persisted or logged. The JSON response uses `private, no-store`; object parameters request attachment disposition and the verified MIME type. Nginx applies MIME nosniff and a restrictive referrer policy to the application.

Database and object storage cannot share a transaction. Uploads use compensating deletion when database persistence fails. Claim/account exports read authorized objects into request-scoped temporary ZIP files and delete each temporary file after sending. Account deletion synchronously removes owned receipt objects before deleting sessions and relational identity; if storage deletion reports failure, relational deletion stops. The current code has no persisted asynchronous export object or scheduled orphan-cleanup subsystem.

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
