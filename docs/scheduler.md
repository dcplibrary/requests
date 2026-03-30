# Scheduler & Queue Setup

This guide covers configuring the Laravel scheduler and queue worker for `dcplibrary/requests` in a Docker environment.

## Prerequisites

- Docker Compose with a working `requests` web container
- Percona/MySQL database container
- The `dcplibrary/requests` package installed in the host Laravel app

## Docker Compose Containers

Add `requests-scheduler` and `requests-queue` services alongside the main web container. Example `docker-compose.yml` excerpt:

```yaml
  requests-scheduler:
    image: your-php-image:latest
    container_name: requests-scheduler
    restart: unless-stopped
    volumes:
      - ./app:/var/www
    working_dir: /var/www
    command: >
      sh -c "while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done"
    depends_on:
      - requests-db

  requests-queue:
    image: your-php-image:latest
    container_name: requests-queue
    restart: unless-stopped
    volumes:
      - ./app:/var/www
    working_dir: /var/www
    command: php artisan queue:work --sleep=1 --tries=3 --timeout=90
    depends_on:
      - requests-db
```

> **Note:** Both containers share the same volume as the web container so they use the same `.env` and codebase.

## Queue Configuration

1. Set the queue connection in `.env`:

```
QUEUE_CONNECTION=database
```

2. Publish and run the Laravel queue migrations if not already present:

```bash
php artisan queue:table
php artisan migrate
```

The queue handles background jobs such as Polaris patron lookups, backup pruning, and **patron/staff notification mail** from `NotificationService` (when the bus is available and `REQUESTS_QUEUE_NOTIFICATION_MAIL` is not `false`). Ensure a worker is running or set `QUEUE_CONNECTION=sync` for immediate in-process delivery.

## Scheduled Laravel log pruning (package-managed)

The package registers **`requests-package-prune-laravel-logs`** when `config('requests.log_pruning.enabled')` is true (default). It runs `php artisan requests:prune-logs` on the cron in `requests.log_pruning.cron` (default `15 3 * * *` — daily at 3:15 AM, offset from the default backup window).

- Deletes `*.log` files in `storage/logs` (or `REQUESTS_LOG_PRUNING_PATH` if set) whose **mtime** is older than `requests.log_pruning.retention_days` (default **14**).
- Output is appended to `storage/logs/requests-log-prune.log`.
- **Disable:** set `REQUESTS_LOG_PRUNING_ENABLED=false` in `.env`, or publish `config/requests.php` and set `'enabled' => false` under `log_pruning`.
- **Manual run:** `php artisan requests:prune-logs` (add `--dry-run` to preview).

Env keys: `REQUESTS_LOG_PRUNING_ENABLED`, `REQUESTS_LOG_RETENTION_DAYS`, `REQUESTS_LOG_PRUNING_CRON`, `REQUESTS_LOG_PRUNING_PATH` (optional; must still resolve under the app `storage/` directory).

## Scheduled backups (package-managed)

The package registers a scheduler event from **Settings → Backups → Automated backup schedule** (stored as `backup_schedule_*` settings). When **Enable scheduled backups** is on, `php artisan schedule:run` executes `requests:backup` with the flags you chose (config, database, storage, prune) and optional output path.

- **Do not** duplicate the same backup in `routes/console.php` unless you disable the package schedule or you want two runs.
- Timing uses a **five-field cron** expression (same as system cron), evaluated in the **application timezone** (`APP_TIMEZONE` / `config/app.php`).
- Output is appended to `storage/logs/requests-backup.log`.

### Legacy: register only in the host app

If you prefer not to use the settings UI, you can instead register in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('requests:backup --config --db --prune')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/requests-backup.log'));
```

`--all` is shorthand for `--config --db --storage` and will create a **full storage zip on every run** (often huge). Add `--storage` only when you want that. Keep **scheduled backups disabled** in Settings if you use this approach, so the job does not run twice.

### Verifying the Scheduler

Check that the scheduler container is running:

```bash
docker ps --filter name=requests-scheduler
```

List registered scheduled tasks:

```bash
docker exec requests php artisan schedule:list
```

Run the scheduler manually to test:

```bash
docker exec requests php artisan schedule:run
```

## Timezone Configuration

The application timezone controls when scheduled tasks execute and how timestamps display.

### Setting the Timezone

1. In `.env`, set:

```
APP_TIMEZONE=America/Chicago
```

2. Ensure `config/app.php` reads from the environment:

```php
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

3. Clear config cache after changes:

```bash
docker exec requests php artisan config:clear
```

### Verifying the Timezone

```bash
docker exec requests php artisan tinker --execute="echo now()->timezone->getName() . ' — ' . now();"
```

Expected output should show `America/Chicago` (or your configured timezone) and the correct local time.

## Troubleshooting

### Scheduled tasks run at wrong time
- **Cause:** `config/app.php` has `'timezone' => 'UTC'` hardcoded instead of reading from `.env`.
- **Fix:** Change to `'timezone' => env('APP_TIMEZONE', 'UTC')` and clear config cache.

### Scheduler container running but no tasks execute
- **Cause:** No tasks registered in `routes/console.php`.
- **Fix:** Add `Schedule::command(...)` calls as shown above. Run `php artisan schedule:list` to verify.

### Queue jobs not processing
- **Cause:** Queue worker container not running, or `QUEUE_CONNECTION` set to `sync`.
- **Fix:** Verify `QUEUE_CONNECTION=database` in `.env`, check `docker ps --filter name=requests-queue`, and inspect `failed_jobs` table.

### Backup command fails with "No backup type selected"
- **Cause:** Missing flags.
- **Fix:** Pass at least one of `--config`, `--db`, `--storage`. Use `--all` only when you intentionally want all three (including a full `storage/app` zip).

### Storage zips grow huge or duplicate each other
- **Cause:** Older versions zipped all of `storage/app`, including `requests-backups` and prior `requests-storage-*.zip` files.
- **Fix:** Update the package; storage exports now skip the backup directory and prior storage zip names. Prefer explicit `--config --db` in cron instead of `--all` unless you need daily storage archives.

### Config cache stale after `.env` changes
- **Cause:** Laravel caches config on first boot.
- **Fix:** Run `php artisan config:clear` inside the container after any `.env` change. If using `config:cache`, re-run it.
