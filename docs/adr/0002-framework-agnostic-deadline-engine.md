# ADR 0002: Keep Deadline Calculation Framework-Agnostic

- Status: Accepted
- Date: 2026-07-20

## Context

Return and warranty dates are KeepProof's central business result. Their behavior includes inclusive and exclusive policies, calendar periods, manual overrides, leap years, month ends, timezones, and an explanation of the applied rule. Embedding this logic in controllers, Eloquent models, or Carbon helpers would make it harder to reason about and reuse.

The package must remain focused on purchase deadlines rather than become a universal rules engine.

## Decision

Implement packages/deadline-engine as an independent Composer package on PHP 8.5.8. It uses strict types, PSR-4, immutable value objects, PHP enums, and DateTimeImmutable-compatible inputs. It has no Laravel, database, queue, HTTP, or storage dependency.

The input describes the purchase date, timezone, return and warranty periods, inclusive or exclusive semantics, and optional manual overrides. The result contains each deadline, whether it was calculated or overridden, a stable rule code, and a human-readable explanation payload.

Calendar behavior is explicit:

- Calendar-day periods and calendar-month/year periods are distinct.
- End-of-month behavior is tested and documented.
- A deadline remains a local calendar date; delivery instants are derived later by the application.
- Manual overrides win and are identified as such.

The package receives exhaustive table-driven tests and property-style invariants for order, determinism, leap dates, month ends, and timezone transitions. Laravel maps request/database values to package value objects and persists result snapshots with the engine version.

## Consequences

- Core business rules can be reviewed and tested without booting Laravel.
- Symfony and Yii examples can later consume a real package rather than copied logic.
- Mapping code is required at the Laravel boundary.
- The package does not know retailer policy, legislation, notification schedules, or persistence.
- Changing calculation semantics requires versioned tests and a deliberate recalculation policy for existing purchases.

## Alternatives rejected

- Eloquent model methods or Carbon macros: couple central rules to Laravel and invite hidden mutable state.
- A database-configured rules engine: far broader than the actual problem.
- Automatic country-law tables: high maintenance and legal-risk surface outside the product scope.
