# Database Migration Runbook

KeepProof uses Laravel migrations against PostgreSQL. Production migrations are an explicit deployment step; containers do not run them automatically at startup. This prevents every replica from racing to change the schema and makes failures visible before traffic moves.

## Migration design rules

- Use expand-and-contract changes when old and new application versions overlap.
- Keep migrations small and deterministic. Do not call external providers or enqueue jobs from a schema migration.
- Add nullable/new columns or parallel tables first, deploy compatible code, backfill in bounded batches, switch reads/writes, and remove old structures in a later release.
- Create large indexes using a PostgreSQL-safe online strategy where required; Laravel's default transactional behavior is not a substitute for estimating locks.
- Treat data backfills as observable application commands/jobs with checkpoints, not as an unbounded `up()` loop.
- Ensure workers can process payloads produced by both versions during a rolling deploy. Stop/drain incompatible workers before changing a payload contract.
- Do not edit a migration that may have run outside your machine. Add a new corrective migration.
- A migration must not depend on demo seed data.

## Pre-deployment

1. Identify the exact commit/image and review every new migration plus its rollback behavior.
2. Run the complete quality suite and a clean-database migration:

    ```bash
    make quality
    make test
    make e2e
    docker compose run --rm backend php artisan migrate:fresh --seed --force
    ```

3. Test the upgrade from a representative backup, including workers and the previous application version where rolling compatibility matters.
4. Estimate table size, lock duration, temporary disk usage, and backfill time in a production-like dataset. Do not invent estimates from the demo dataset.
5. Create and verify a coherent PostgreSQL/object-storage backup using [BACKUP_RESTORE.md](BACKUP_RESTORE.md).
6. Confirm alerting, operator access, maintenance/rollback decision points, and available disk space.

## Deployment

Use the shared production cache/lock backend so Laravel's isolated migration lock is effective. Run from exactly one trusted release container:

```bash
php artisan migrate:status
php artisan migrate --force --isolated
php artisan migrate:status
```

Recommended order for an expand step:

1. pause schedulers only if they can initiate an incompatible flow;
2. apply backward-compatible schema expansion;
3. deploy API and workers that understand both schemas/payload versions;
4. run and observe bounded backfills;
5. switch reads after reconciliation checks;
6. resume the scheduler and process queues; and
7. verify readiness, error rate, queue depth, failed jobs, and core receipt/deadline flows.

Run contract/reminder/export smoke tests using synthetic records. Confirm that household isolation still returns an indistinguishable not-found response for foreign and absent identifiers.

## Failure handling

Stop and preserve evidence if a migration fails. Capture the migration name, SQLSTATE, PostgreSQL logs, application revision, schema state, lock activity, and whether the migration transaction committed. Do not repeatedly rerun an unknown partial migration.

Prefer a forward corrective migration when:

- new writes may use the expanded schema;
- rollback would discard or reinterpret data;
- a migration created an index or constraint concurrently; or
- old application code is no longer compatible with current queue payloads.

Use `php artisan migrate:rollback --step=1 --force` only after reviewing the exact `down()` method, confirming no post-migration writes would be lost, and validating application compatibility. A code rollback without a compatible schema/worker rollback can worsen the incident.

For unrecoverable corruption, keep the service unavailable and restore the verified backup as a complete database/object set. Record the recovery point and validate authorized file access before reopening traffic.

## Contract phase and cleanup

Destructive cleanup belongs in a later release after telemetry and reconciliation show that no code, worker, queued payload, or export reads the old shape. Take another verified backup, deploy code that no longer references the old schema, and then apply the contract migration.

Update OpenAPI schemas, generated frontend types, architecture/ADR documents, the changelog, backup compatibility notes, and this runbook whenever a migration changes an external contract or recovery procedure.
