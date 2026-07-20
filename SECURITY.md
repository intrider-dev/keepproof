# Security Policy

KeepProof stores purchase records and receipt documents, so vulnerabilities affecting authentication, household isolation, file access, exports, deletion, or log privacy are treated as high priority. This repository is a production-oriented portfolio project, not a hosted service or a claim of ASVS certification.

## Supported versions

No stable release has been published yet.

| Version | Security fixes |
| --- | --- |
| Unreleased `main` | Best-effort pre-release fixes |
| Latest tagged release, once available | Supported |
| Older tags and forks | Not supported |

After the first stable release, only the latest release line will receive fixes unless a security advisory says otherwise.

## Report a vulnerability privately

Use GitHub's private vulnerability reporting:

1. Open this repository's **Security** page.
2. Choose **Advisories**.
3. Select **Report a vulnerability**.

Include the affected revision, impact, prerequisites, the smallest reproducible sequence, and a suggested mitigation if you have one. Use synthetic data. Do not attach a real receipt, account export, cookie, token, signed URL, credential, or unredacted production log.

If the **Report a vulnerability** button is unavailable, open a public issue that only asks the maintainers to enable a private security channel. Do not include vulnerability details in that issue.

Please allow maintainers to investigate and coordinate a fix before public disclosure. Maintainers will acknowledge and triage reports as capacity permits; this volunteer project does not promise a response SLA or a bug bounty. Good-faith research that avoids privacy violations, service disruption, persistence, and data destruction is welcome.

## Security scope

Examples that belong in a private report include:

- cross-household data access or another IDOR/BOLA path;
- authentication, session, email-verification, or password-reset bypass;
- unauthorized receipt, claim-package, account-export, or signed-URL access;
- unsafe upload parsing, path traversal, command injection, or stored XSS;
- duplicate or forged notification/export processing with security impact;
- sensitive data in logs, errors, caches, artifacts, or external-provider requests;
- account deletion that leaves accessible user data; and
- leaked repository or CI credentials.

General hardening suggestions without a concrete vulnerability can use a normal feature request. Reports about third-party packages should identify the package, installed version, advisory, and whether KeepProof reaches the vulnerable code path.

## Threat model summary

The principal assets are account credentials and sessions, household-scoped purchase data, private receipt objects, extraction text, exports, notification metadata, and encryption/signing secrets. Trust boundaries exist between the browser and API, household tenants, the API and queue, private object storage, local OCR processes, optional external AI providers, email delivery, and generated downloads.

The most important threats are tenant escape, forged or replayed state-changing requests, malicious receipt content, unsafe document parsing, prompt injection, signed-URL disclosure, queue replay, reminder duplication, destructive export/deletion abuse, and sensitive telemetry. Core mitigations include server-side policies and scoped queries, stateful cookie authentication with CSRF protection, content-based upload validation, private object storage with short-lived URLs, controlled document-state transitions, idempotent jobs, schema validation, explicit external-AI consent, human confirmation before deadlines activate, and audit records for sensitive operations.

Residual risks and deployment assumptions are documented in [the full threat model](docs/security/THREAT_MODEL.md). The [ASVS checklist](docs/security/ASVS_LEVEL_1.md) records evidence and open verification work without asserting certification.

## Operational security

- Keep secrets outside source control and rotate any credential that may have been exposed.
- Terminate TLS at a trusted ingress and set production-only secure cookie and HSTS settings.
- Keep PostgreSQL, Redis, object storage, mail, workers, and OCR endpoints on private networks.
- Run Composer, npm, dependency-review, CodeQL, and secret-scanning gates in CI.
- Follow the [backup and restore runbook](docs/operations/BACKUP_RESTORE.md) and [migration runbook](docs/operations/MIGRATIONS.md) before deployment.

Security controls must be re-evaluated for a real deployment, including infrastructure IAM, TLS, key management, monitoring, alerting, retention, restore drills, malware scanning, and an incident-response process.
