# Installation

[← Back to README](README.md)

## Requirements

- PHP ^8.3
- Laravel ^12.0
- Livewire ^3.0
- `blashbrook/papiclient` (Polaris ILS client)

## Package Setup

The package is loaded via Composer path repository in the host app (`sfp-laravel`):

```json
"repositories": {
    "0": {
        "type": "path",
        "url": "../sfp"
    }
}
```

The vendor symlink points to the package root:

```
sfp-laravel/vendor/dcplibrary/sfp → ../../../sfp/
```

The service provider is auto-discovered via `composer.json` `extra.laravel.providers`.

## Environment Variables

| Variable | Description |
|----------|-------------|
| `SFP_ADMIN_EMAIL` | Email address for the initial admin user seeded by `UsersSeeder` |
| `SFP_ADMIN_NAME` | Display name for the initial admin user |
| `SFP_ISBNDB_KEY` | ISBNdb v2 API key (read via `config('sfp.isbndb.key')`) |
| `SFP_ROUTE_PREFIX` | URL prefix for all package routes (default: `sfp`) |

## Config File

Publish the config:

```bash
php artisan vendor:publish --tag=sfp-config
```

Or manually create `config/sfp.php` in the host app:

```php
return [
    'route_prefix'    => env('SFP_ROUTE_PREFIX', 'sfp'),
    'middleware'       => ['web'],
    'staff_middleware' => ['web', 'auth'],
    'isbndb' => [
        'key' => env('SFP_ISBNDB_KEY', ''),
    ],
    'queue' => [
        'connection' => env('SFP_QUEUE_CONNECTION', 'redis'),
        'name'       => env('SFP_QUEUE_NAME', 'default'),
    ],
];
```

### Middleware Keys

| Key | Default | Purpose |
|-----|---------|---------|
| `middleware` | `['web']` | Applied to the public patron form route |
| `staff_middleware` | `['web', 'auth']` | Applied to all staff routes |

## Migrations & Seeders

```bash
# Run migrations
php artisan migrate

# Seed all default data
php artisan db:seed --class=Dcplibrary\\Sfp\\Database\\Seeders\\SfpDatabaseSeeder
```

To publish migrations and seeders to the host app (for customization):

```bash
php artisan vendor:publish --tag=sfp-migrations
php artisan vendor:publish --tag=sfp-seeders
```

## Cache Clearing After Updates

After pulling package changes:

```bash
php artisan view:clear
php artisan route:clear
```

## Deployment Workflow (Development)

Changes made in the worktree (`sfp/.claude/worktrees/{name}`) must be merged to the main `sfp` repo for `sfp-laravel` to pick them up (the vendor symlink points to the main repo, not the worktree):

```bash
# In the main sfp repo
git merge claude/{worktree-name} --no-edit

# In sfp-laravel
php artisan view:clear && php artisan route:clear
```
