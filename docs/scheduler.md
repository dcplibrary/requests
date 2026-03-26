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

## Scheduled backups (package-managed)

The package registers a scheduler event from **Settings → Backups → Automated backup schedule** (stored as `backup_schedule_*` settings). When **Enable scheduled backups** is on, `php artisan schedule:run` executes `requests:backup` with the flags you chose (config, database, storage, prune) and optional output path.

- **Do not** duplicate the same backup in `routes/console.php` unless you disable the package schedule or you want two runs.
- Timing uses a **five-field cron** expression (same as system cron), evaluated in the **application timezone** (`APP_TIMEZONE` / `config/app.php`).
- Output is appended to `storage/logs/requests-backup.log`.

### Legacy: register only in the host app

If you prefer not to use the settings UI, you can instead register in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('requests:backup --all --prune')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/requests-backup.log'));
```

Keep **scheduled backups disabled** in Settings if you use this approach, so the job does not run twice.

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
- **Fix:** Use `--all` for a complete backup, or specify `--config`, `--db`, and/or `--storage`.

### Config cache stale after `.env` changes
- **Cause:** Laravel caches config on first boot.
- **Fix:** Run `php artisan config:clear` inside the container after any `.env` change. If using `config:cache`, re-run it.
