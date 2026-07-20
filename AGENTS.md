# Repository Working Conventions

## Branch names

- Name branches after the change they deliver, not the tool or person producing the change.
- Use `<type>/<short-kebab-case-description>`.
- Allowed types are `feature`, `fix`, `chore`, `docs`, `test`, `refactor`, `perf`, and `ci`.
- Examples: `feature/claim-package-export`, `fix/receipt-preview-url`, and `docs/security-model`.
- Do not use assistant, automation, IDE, vendor, or contributor identities as branch-name prefixes.

## Git history

- Use Conventional Commits and describe the product or engineering outcome.
- Preserve truthful Git authorship and provenance.
- Do not rewrite published history solely for cosmetic reasons. Rebase or squash only when it improves reviewability and no downstream work depends on the commits.
- Never force-push the default branch.
