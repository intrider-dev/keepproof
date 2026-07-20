# ADR 0003: Process Documents Locally by Default

- Status: Accepted
- Date: 2026-07-20

## Context

Receipts can reveal names, addresses, payment fragments, shopping habits, and order identifiers. The primary workflow must work without transmitting that content to a paid or external AI provider. OCR and structured extraction are also failure-prone, so manual entry cannot be treated as an exceptional path.

Running a separate Python service solely to advertise another language would add an unnecessary network and deployment boundary.

## Decision

Document processing stays inside the Laravel modular monolith and its queue workers:

- DeterministicFixtureExtractor maps approved synthetic fixture hashes to deterministic results.
- A local Tesseract adapter runs through Symfony Process with argument arrays, no shell interpolation, bounded time/output/resources, and no network requirement.
- Manual entry and correction remain complete first-class paths.
- ReceiptDataExtractor and DocumentTextExtractor interfaces isolate provider boundaries.

Raw OCR text is stored separately from normalized extracted fields and confirmed purchase data. It is encrypted at the application layer and excluded from logs.

An optional external LLM adapter may be enabled only when all of these conditions hold:

- The feature flag and credentials are configured.
- The user gives explicit consent for the individual document.
- Redaction runs before transmission.
- The adapter has strict timeout, bounded retry, and token/output limits.
- The response is accepted only after strict JSON Schema validation.
- Provider, model, prompt version, and schema version are recorded.
- Receipt text is treated as untrusted data; no commands or tool calls are supported.

The external adapter is provider-agnostic and disabled by default. No chain of thought is requested, stored, or logged.

## Consequences

- The demo and main workflow run offline apart from downloading initial dependencies/images.
- Local OCR quality is intentionally bounded, and the review UI is essential.
- Queue containers need Tesseract and safe PDF rasterization dependencies.
- External AI can improve extraction without becoming a source of truth or a required service.
- A separate OCR service remains a later option only if profiling demonstrates isolation or scaling value.

## Alternatives rejected

- External LLM as the only extraction path: conflicts with privacy, reliability, and local-demo requirements.
- Browser-only OCR: inconsistent performance and a larger frontend security/performance burden.
- Immediate FastAPI microservice: no justified independent data ownership or scaling boundary.
