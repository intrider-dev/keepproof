# ADR 0004: Require Human Confirmation Before Activating Extracted Deadlines

- Status: Accepted
- Date: 2026-07-20

## Context

OCR and model extraction can confuse dates, totals, policy periods, and order numbers. An incorrect return date can be more harmful than no automated date because it creates false confidence. Confidence scores alone do not prove correctness.

Manual entry is already an explicit human assertion, while machine-extracted data is provisional.

## Decision

Extraction results enter the needs_review document state and remain separate from authoritative purchase fields. The API returns, for every field, its raw value, normalized value, confidence, source, and validation errors.

No extracted deadline becomes active and no reminder is scheduled until an authorized household member submits an explicit confirmation. Confirmation:

1. Validates that the document is in `needs_review`.
2. Applies authoritative purchase data and replaces its items.
3. Calls the Deadline Engine and snapshots each date, source, rule summary, and trace.
4. Reconciles reminder schedules.
5. Records accepted field values and corrections.
6. Locks the document during its controlled transition to `ready` and records an audit event.

These writes occur in one database transaction and the endpoint requires an idempotency key backed by a unique database record. Concurrent confirmation that loses the final state transition rolls back its complete transaction. A validation or persistence failure also rolls back the entire confirmation.

A user-created manual purchase is activated by its explicit save/confirm action and does not require a synthetic OCR review.

## Consequences

- The product never presents AI output as unquestioned fact.
- Users incur one review step after extraction.
- Dashboard and reminder queries can rely only on active deadline rows.
- Corrections provide testable evidence about extraction quality without retaining model reasoning.
- Re-extraction creates a new review candidate and cannot silently replace confirmed data.

## Alternatives rejected

- Auto-activate fields above a confidence threshold: confidence is not calibrated well enough for deadline risk.
- Activate then ask the user to correct: can send incorrect reminders before review.
- Store extraction directly on purchases: loses the boundary between evidence, proposal, and user-confirmed truth.
