# ADR 0008: Stream Receipt Downloads Through a Same-Origin Gateway

- Status: Accepted
- Date: 2026-07-20

## Context

The application writes receipts through an internal S3-compatible endpoint named `object-storage` on the Compose network. Flysystem used that endpoint when creating an S3 presigned URL. The signature was valid inside Docker, but the host browser could not resolve the internal DNS name. Replacing the hostname after signing is not valid because AWS Signature Version 4 covers the `Host` header.

KeepProof must keep receipts private, work on a fresh local environment without provider-specific networking, and preserve short-lived access. Files are already limited to 10 MiB.

## Decision

Object storage remains internal and publishes no host port. The authenticated tenant-scoped POST endpoint authorizes the document and returns a relative Laravel temporary signed route, five minutes by default and bounded to 1–15 minutes.

Consuming that URL requires all of the following:

1. the active first-party web session;
2. an unmodified, unexpired relative signature;
3. current household membership through the document Policy;
4. a document that is neither quarantined nor marked as having its original removed; and
5. an object that still exists on the configured private disk.

The backend streams the object with its verified MIME type, a code-owned attachment filename, `nosniff`, and `private, no-store` caching. The storage key and endpoint are never returned. Nginx access logs use `$uri`, not `$request_uri`, so signature query parameters are not recorded.

## Consequences

- Browser routing is independent of Docker DNS and S3 endpoint topology.
- Authorization and document state are checked again when the URL is consumed, so quarantine or original deletion revokes an already minted link.
- Storage credentials, bucket names, and internal endpoints do not become frontend configuration.
- Receipt bytes pass through PHP/Nginx. The 10 MiB input bound makes that an explicit, acceptable MVP cost, but it is not the highest-throughput design.
- A measured larger deployment may introduce a separately configured public S3/CDN signing endpoint. That change must retain bounded expiry, trusted disposition, log hygiene, tenant authorization before minting, and provider-specific bucket/IAM tests.

## Alternatives rejected

- Rewrite the internal hostname after signing: invalidates Signature Version 4.
- Publish the local S3 endpoint and maintain separate internal/public signer disks: adds deployment-sensitive configuration and exposes storage topology without a current throughput need.
- Permanent or public object URLs: incompatible with the privacy model.
- Return all bytes from the authorizing POST: makes ordinary browser download behavior and expiry semantics less clear, while still consuming backend bandwidth.
