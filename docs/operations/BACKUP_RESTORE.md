# Backup and Restore Runbook

This runbook covers the Docker Compose development/demo environment and defines requirements for a real deployment. It does not promise an RPO or RTO. Measure both through restore drills in the target infrastructure.

PostgreSQL and private object storage form one logical backup set. Redis is a cache/queue transport, not a source of truth. Mailpit contains local development mail only and is not backed up.

## Production requirements

- Use encrypted, access-controlled, off-site PostgreSQL backups with point-in-time recovery where the provider supports it.
- Enable private object-store versioning or immutable backup copies, encryption, and lifecycle rules compatible with account deletion requirements.
- Keep backup credentials separate from application runtime credentials and grant restore rights only to operators who need them.
- Escrow the application encryption/signing key and other required secrets in a separate secrets-management backup. A database restore without the original `APP_KEY` cannot decrypt encrypted values; generating a new key is not a restore.
- Store a manifest with application revision, database engine version, migration state, object snapshot/version marker, creation time in UTC, and checksums.
- Monitor backup jobs and perform documented restores into an isolated environment. A successful upload is not a verified backup.
- Define retention, legal/privacy constraints, RPO, and RTO for the actual service. Account deletion must also cover retained versions according to the published policy.

## Create a coherent local backup

The commands below assume the default Compose project and local-only credentials from `.env.example`. Replace values when your environment differs. The brief maintenance window prevents writes while the database and object data are captured.

1. Confirm the exact project and destination. The destination must be outside a directory removed by `make reset`.

    ```bash
    docker compose ps
    mkdir -p backups/keepproof-local
    git rev-parse HEAD
    ```

2. Stop application writers, then let any in-flight PostgreSQL operation finish.

    ```bash
    docker compose stop nginx frontend backend scheduler queue-worker
    ```

3. Create a custom-format database dump inside the PostgreSQL container and copy it out.

    ```bash
    docker compose exec -T postgres pg_dump \
      --username=keepproof \
      --dbname=keepproof \
      --format=custom \
      --file=/tmp/keepproof.dump
    docker compose cp postgres:/tmp/keepproof.dump backups/keepproof-local/database.dump
    docker compose exec -T postgres rm -f /tmp/keepproof.dump
    ```

4. Stop object storage and copy its data directory. This physical copy is specific to the pinned local SeaweedFS layout; use the provider's supported snapshot/export mechanism in production.

    ```bash
    docker compose stop object-storage
    mkdir -p backups/keepproof-local/object-storage
    docker compose cp object-storage:/data/. backups/keepproof-local/object-storage/
    ```

5. Record metadata and checksums. On macOS use `shasum -a 256` if `sha256sum` is unavailable.

    ```bash
    git rev-parse HEAD > backups/keepproof-local/git-revision.txt
    docker compose exec -T postgres postgres --version > backups/keepproof-local/postgres-version.txt
    (cd backups/keepproof-local && \
      find . -type f ! -name SHA256SUMS -print0 \
        | sort -z \
        | xargs -0 sha256sum > SHA256SUMS)
    ```

6. Restart services and verify readiness.

    ```bash
    docker compose up --detach --wait
    curl --fail http://localhost:8080/health/ready
    ```

Do not commit the `backups/` directory. It may contain all private application data even when the original demo was synthetic.

## Restore into an isolated local environment

Restore is destructive to the selected Compose project's data. Before continuing, run `docker compose ls` and `docker compose ps` and confirm that the project name is `keepproof` and that no wanted local data remains.

1. Verify backup checksums from the directory that contains `SHA256SUMS`.

    ```bash
    cd backups/keepproof-local
    sha256sum --check SHA256SUMS
    cd ../..
    ```

2. Remove only KeepProof's named development volumes, recreate infrastructure, and confirm container health.

    ```bash
    make reset
    docker compose up --detach --wait postgres redis object-storage
    ```

3. Stop object storage, copy its saved data, then start it again.

    ```bash
    docker compose stop object-storage
    docker compose cp backups/keepproof-local/object-storage/. object-storage:/data/
    docker compose start object-storage
    ```

4. Copy and restore the PostgreSQL dump. `--clean --if-exists` replaces objects in the newly created local database.

    ```bash
    docker compose cp backups/keepproof-local/database.dump postgres:/tmp/keepproof.dump
    docker compose exec -T postgres pg_restore \
      --username=keepproof \
      --dbname=keepproof \
      --clean \
      --if-exists \
      --no-owner \
      --no-privileges \
      /tmp/keepproof.dump
    docker compose exec -T postgres rm -f /tmp/keepproof.dump
    ```

5. Restore the original `APP_KEY` through the environment's secret mechanism. Build the application without running `make setup`, because `make setup` intentionally generates a fresh local key. Install dependencies, apply forward-compatible migrations, and inspect status.

    ```bash
    docker compose build backend frontend
    docker compose run --rm backend composer install --no-interaction --prefer-dist
    docker compose run --rm frontend npm ci
    docker compose run --rm backend php artisan migrate --force --isolated
    docker compose run --rm backend php artisan migrate:status
    make up
    ```

6. Validate the restore using synthetic or authorized data only:

    ```bash
    curl --fail http://localhost:8080/health/live
    curl --fail http://localhost:8080/health/ready
    make test
    ```

Then sign in, inspect record counts, download at least one authorized receipt, verify that a foreign household cannot access it, and exercise one queued extraction/reminder operation. Record elapsed restore time and any manual intervention; that measured drill establishes the environment's real RTO.

## Failure and rollback

- Keep the original backup immutable when a restore fails; work from a copy.
- A PostgreSQL dump without the matching objects is incomplete. Do not expose a partially restored environment to users.
- If object storage restore fails, keep the API in maintenance mode and investigate provider/version compatibility.
- If migrations fail after restore, follow the [migration runbook](MIGRATIONS.md); prefer a forward fix to a data-destructive rollback.
- Rotate credentials if a backup location or manifest was exposed, and follow the incident process for any private data disclosure.
