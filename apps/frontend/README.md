# KeepProof Web

Vue 3 and TypeScript SPA for KeepProof, a private receipt, return-window, and warranty tracker.

## Local development

    npm ci
    npm run dev

The default environment enables a browser-only demo with deterministic synthetic receipts. It does not require the Laravel API.

- Email: demo@keepproof.local
- Password: KeepProof!2026
- Fast path: open /demo

## Architecture

- src/router.ts defines public, authentication, and protected product routes.
- src/demo contains deterministic fixtures and the in-memory demo boundary.
- src/lib/api.ts is the same-origin, credentialed fetch client. Unsafe requests initialize Laravel Sanctum CSRF protection and forward the XSRF token.
- src/pages contains route-level product screens.
- src/components contains accessible shared UI.

The demo store is deliberately separate from the API client. Replacing fixture-backed actions with generated OpenAPI calls does not change the route or component model.

## Quality commands

    npm run format:check
    npm run lint
    npm run typecheck
    npm run test:unit
    npm run build
    npm run test:e2e

npm run quality runs every check except Playwright. Playwright artifacts and deterministic reference screenshots are written to ../../output/playwright.

Generate the typed API client after the backend OpenAPI document exists:

    npm run generate:api

## Interaction and accessibility baseline

The interface is mobile-first, keyboard navigable, and designed for WCAG 2.2 AA contrast and focus behavior. Motion is limited to short transform/opacity feedback, hover effects are restricted to fine pointers, and reduced-motion preferences remove spatial movement.
