# ADR 0001: Use a Modular Monolith

- Status: Accepted
- Date: 2026-07-20

## Context

KeepProof needs authentication, tenant authorization, purchases, document processing, reminders, exports, and privacy workflows. These concerns have different responsibilities, but they share transactions and are delivered by a small project. Splitting them into network services would add deployment, consistency, tracing, and failure-handling costs before an independent scaling need exists.

The portfolio should demonstrate clear boundaries without disguising ordinary Laravel code behind unnecessary repositories, buses, or interfaces.

## Decision

The backend is one Laravel 13.20 application and one PostgreSQL 18.4 database, deployed with separate HTTP, queue-worker, and scheduler processes from the same codebase.

Code is organized by capability: Accounts, Households, Purchases, Documents, Reminders, Exports, and Audit. Controllers and commands call small verb-named actions. Actions use Eloquent directly when persistence is not an external boundary. Laravel policies provide authorization, request objects validate input, and API resources serialize output.

Cross-module critical work uses explicit action calls inside a database transaction. Events are used only for after-commit wake-ups and side effects that can be reconstructed. The deadline package is the intentional exception: it is framework-agnostic under packages/deadline-engine.

Dependency direction is:

1. Delivery adapters to application actions.
2. Accounts and Households to tenant-aware capabilities.
3. Documents to the confirmed Purchase write boundary.
4. Purchases to the deadline package.
5. Reminders to active deadlines and user preferences.
6. Exports and privacy actions as top-level orchestrators.

## Consequences

- Local setup, migrations, transactions, testing, and deployment remain understandable.
- Tenant and extraction confirmation invariants can be committed atomically.
- Module boundaries rely on naming, review, and focused dependency tests rather than network isolation.
- Worker and HTTP load can scale independently while using the same artifact.
- A module can be extracted later only when data ownership, failure isolation, or scaling evidence justifies it.

## Alternatives rejected

- Microservices: disproportionate operational and consistency cost.
- A single conventional Controllers/Models/Services layout: weak ownership as the application grows.
- CQRS, event sourcing, or a generic application bus: no product requirement warrants their complexity.
- Repository interfaces for Eloquent: they would duplicate the framework without isolating a genuine external dependency.
