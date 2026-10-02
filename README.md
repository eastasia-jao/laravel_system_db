# Inventory System

Laravel application for multi-store inventory, sales, transfers, returns, reporting, and staff workflows.

## Requirements

- PHP 8.2 or later with the extensions required by Laravel and the configured database driver
- Composer
- Node.js 20 or later and npm
- SQLite for local/test use, or a supported production database such as MySQL

## Local setup

In PowerShell, from the repository root:

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Set the database connection and credentials in `.env`, then run:

```powershell
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan storage:link
```

For local development, `composer run dev` starts the web server, queue processes, log tail, and Vite. Product file imports and exports use the dedicated `inventory` queue connection; leave its worker running while testing these workflows.

## Main workflows

- Admin and inventory staff manage shared catalog data, branch stock, imports/exports, and approvals.
- Sales staff work within their assigned store hubs and sales channels.
- Inventory actions are recorded in transaction and activity logs. Imports and transfers use transactional updates to avoid partial stock changes.
- Product import/export requests run on the `product-files` queue. Keep [the scaling and worker guide](./docs/inventory-scaling.md) available to operators.

## Testing and builds

```powershell
composer test
npm test
npm run build
```

The PHP feature tests use an isolated in-memory SQLite database. They do not replace staging tests against the production database engine or concurrent-user tests. CI runs these commands on pushes and pull requests.

## Deployment

1. Install PHP and Node dependencies and configure production `.env` values. Keep `APP_DEBUG=false`, use HTTPS, and use a unique `APP_KEY`.
2. Build frontend assets with `npm ci` and `npm run build`.
3. Review pending migrations with `php artisan migrate:status`, then apply them with `php artisan migrate --force`. The query-path index migration is additive, but index builds can consume I/O, disk, and write capacity; test it on staging with the production database engine and schedule the production rollout for a low-traffic window.
4. Ensure `storage` and `bootstrap/cache` are writable by the application account.
5. Run a supervised queue worker for the dedicated queue:

   ```powershell
   php artisan queue:work inventory --queue=product-files --sleep=2 --tries=1 --timeout=600 --memory=256
   ```

   Use a process supervisor in production so the worker restarts after exit or host restart. After deploying code, run `php artisan queue:restart` and verify the replacement worker is online.
6. Run `php artisan inventory:health` and review `php artisan queue:failed` after deployment. Investigate failed jobs before retrying them; import/approval retries must not be submitted concurrently.
7. Configure encrypted, off-host backups of the database **and** `storage/app/private` and `storage/app/public`. The database alone does not contain uploaded proofs, attachments, or files. See [Backup and restore](#backup-and-restore).

## Backup and restore

Backups contain customer, staff, sales, inventory, and uploaded-file data. Encrypt them, restrict access, keep them outside the web root and repository, apply a retention policy, and regularly test restores in a non-production environment. Preserve the production `APP_KEY` securely; changing it can make encrypted application data unreadable.

### Backup

Schedule database-native backups using credentials supplied through a protected database-client configuration or secrets manager (do not put passwords in command history or backup scripts):

- **MySQL/MariaDB:** use `mysqldump --single-transaction --routines --triggers --databases <database> --result-file=<secure-path>`.
- **PostgreSQL:** use `pg_dump --format=custom --file=<secure-path> <database>`.
- **SQLite:** stop web and queue writers first, then copy the configured database file to the protected backup location. Do not back up a live SQLite file with a plain file copy.

Back up `storage/app/private` and `storage/app/public` from the same recovery point, or configure an equivalent versioned backup for the storage provider if uploads are stored remotely. Keep the `.env`/encryption key in a separate secure secret store; do not include it in ordinary downloadable backups.

### Restore drill

1. Confirm the target database and storage paths, and verify the backup files and checksums.
2. Put the application into maintenance mode and stop all queue workers and scheduled processes.
3. Restore the database using the matching database client and restore the backed-up private/public files to the configured storage provider. Never restore a production backup over a live database without an approved maintenance window.
4. Restore the original application key and matching configuration, then run `php artisan migrate:status` and `php artisan inventory:health`.
5. Start the application and supervised workers, then smoke-test login, product lookup, stock balances, reports, and access to a private attachment.
6. Record the restore date, backup source, duration, and any issues; return the application to service only after checks pass.

## History retention

- Read notifications are removed after 90 days. Unread notifications are retained.
- Completed or rejected product import/export requests, including their stored CSV chunks, are removed 90 days after their final update. Pending or processing requests are retained.
- Staff activity logs, sales, payments, returns, replacements, and inventory movements are not automatically deleted. Keep at least three years readily searchable and retain older records in a protected archive. Keep financial and tax-related records for the period required by your accountant and applicable regulations; use ten years as the conservative planning target until that period is confirmed.
- Back up the database and uploaded proof files before configuring retention. Deleting transaction history can break audit, reconciliation, and return workflows; archive older business records before any approved deletion.

The weekly `history:prune` schedule removes only the two short-lived record types above. To preview the eligible counts without deleting anything, run `php artisan history:prune --dry-run`. Production must run Laravel's scheduler every minute (`php artisan schedule:run`); configure the host scheduler/service accordingly. The command accepts `--notifications-days=N` and `--file-requests-days=N` overrides for a manual run.

## Scaling notes

See [docs/inventory-scaling.md](./docs/inventory-scaling.md) for catalog normalization, bounded product loading, import/export processing, queue recovery, and known capacity-test limits.
