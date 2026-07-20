# Local Performance Baseline

This is a small, reproducible development measurement, not a production capacity claim. It exists to catch large regressions in one read path and one upload boundary.

## Command

From a configured checkout:

```bash
make load
```

On Windows PowerShell:

```powershell
./scripts/kp.ps1 load
```

The command refreshes the synthetic demo account, authenticates through the same Sanctum and CSRF flow as the SPA, performs three unmeasured warm-up reads, and runs both scenarios. Workload sizes can be changed with `LOAD_READ_REQUESTS`, `LOAD_READ_CONCURRENCY`, `LOAD_UPLOAD_REQUESTS`, and `LOAD_UPLOAD_CONCURRENCY`.

Implementation: [`tests/load/run.mjs`](../../tests/load/run.mjs)

## Recorded run

- Measured at: 2026-07-20 14:13:08 UTC
- Revision under test: local `main` after the connected Docker workflow fixes; the load harness itself was uncommitted during measurement
- Host: Windows 11 Pro 10.0.26200, AMD Ryzen 7 9800X3D, 8 cores / 16 logical processors, 63.6 GiB RAM
- Docker allocation reported by the engine: 16 CPUs, 31.1 GiB RAM
- Docker Engine: 29.5.3; Docker Compose: 5.1.4
- Application path: development Compose stack, Nginx 1.30.4 to Laravel 13.20.0 on PHP 8.5.8
- Database: PostgreSQL 18.4
- Object storage: SeaweedFS 4.39 through its S3 API
- Queue: Redis 8.2.7 with a separate Laravel worker
- Read dataset at measurement time: one tenant with four purchases, two receipt documents, active deadlines, reminders, and one review item from `DemoDatasetSeeder`
- Upload fixture: synthetic 8,809-byte PNG; no personal data

| Scenario                        | Requests | Concurrency | Failures |  Throughput | Latency min / avg / p50 / p95 / max     |
| ------------------------------- | -------: | ----------: | -------: | ----------: | --------------------------------------- |
| Authenticated dashboard read    |       50 |           5 |        0 | 15.52 req/s | 64.7 / 309.5 / 314.4 / 366.8 / 369.9 ms |
| Private receipt upload accepted |        5 |           1 |        0 |  7.18 req/s | 98.7 / 139.3 / 107.4 / 261.6 / 261.6 ms |

Measured scenario durations were 3,220.7 ms and 696.7 ms respectively. The complete `kp.ps1 load` command also built service readiness, migrations, storage preparation, and seeding into its 73.5-second wall time; that setup time is not part of the request metrics.

## What the upload number includes

Upload latency ends when the API returns `202 Accepted`. By that point KeepProof has:

- validated the multipart request and idempotency key;
- content-sniffed and sanitized the image;
- stored the object privately;
- committed the draft purchase, document, transition journal, audit event, and idempotency record;
- recorded and attempted the durable queue dispatch.

It does not include asynchronous OCR, structured extraction, or the later human-confirmation transaction.

## Limitations

- The development backend uses PHP's local server, not the production PHP-FPM image.
- The run shares one authenticated browser-like session and one local machine; it does not model geographic latency, multiple accounts, TLS termination, or a managed database.
- Five uploads are intentionally below the endpoint's abuse-prevention limit. This is a regression probe, not a saturation test.
- Queue and storage services share the same Docker Desktop VM and physical disk.
- One run is reported. Use repeated, isolated runs and production-like telemetry before making sizing decisions.
