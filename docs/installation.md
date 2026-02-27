# Installation

[← Back to README](README.md)

## Requirements

- PHP ^8.3
- Laravel ^12.0
- Livewire ^3.0 or ^4.0
- `blashbrook/papiclient` (Polaris ILS client)
- `dcplibrary/entra-sso` ^1.6 (Azure Entra ID / OIDC authentication)

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

### SFP Package

| Variable | Description |
|----------|-------------|
| `SFP_ADMIN_EMAIL` | Email address for the initial admin user seeded by `UsersSeeder` |
| `SFP_ADMIN_NAME` | Display name for the initial admin user |
| `ISBNDB_API_KEY` | ISBNdb v2 API key (read via `config('sfp.isbndb.key')`) |
| `SFP_ROUTE_PREFIX` | URL prefix for all package routes (default: `sfp`) |
| `SFP_GUARD` | Auth guard name (default: `null`, uses app default) |
| `SFP_QUEUE_CONNECTION` | Queue connection for background jobs (default: `redis`) |
| `SFP_QUEUE_NAME` | Queue name for background jobs (default: `default`) |

### Entra SSO (Azure AD)

| Variable | Description |
|----------|-------------|
| `ENTRA_TENANT_ID` | Azure AD tenant ID |
| `ENTRA_CLIENT_ID` | OAuth app client ID |
| `ENTRA_CLIENT_SECRET` | OAuth app client secret |
| `ENTRA_REDIRECT_URI` | Callback URL (default: `{APP_URL}/auth/entra/callback`) |
| `ENTRA_AUTO_CREATE_USERS` | Auto-create `users` record on first login (default: `true`) |
| `ENTRA_SYNC_GROUPS` | Sync Azure AD group membership on login (default: `true`) |
| `ENTRA_SYNC_ON_LOGIN` | Re-sync groups on every login (default: `true`) |
| `ENTRA_GROUP_ROLES` | Group-to-role mapping, e.g. `"SFP Admins:admin,SFP Selectors:selector"` |
| `ENTRA_DEFAULT_ROLE` | Default role for users not matched by `ENTRA_GROUP_ROLES` (leave blank to block) |
| `ENTRA_REDIRECT_AFTER_LOGIN` | Post-login redirect path (default: `/sfp/staff`) |

> **Note:** Group names in `ENTRA_GROUP_ROLES` are case-sensitive and must exactly match the Azure AD display names.

## How Staff Access Works

1. User clicks "Sign in with Microsoft" → authenticates via Azure Entra ID.
2. `dcplibrary/entra-sso` syncs their Azure group membership and sets `role` on the `users` table using `ENTRA_GROUP_ROLES`.
3. The SFP `sfp.role` middleware runs on every staff route. If the user has no `sfp_users` record but their `users.role` is `admin` or `selector`, a `sfp_users` record is **auto-provisioned** (active, same role).
4. Users with any other role (or no Entra group match) see the no-access page.

Admins can then manage the user's selector group assignments via **Settings → Users**.

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
    'guard'            => env('SFP_GUARD', null),
    'isbndb' => [
        'key' => env('ISBNDB_API_KEY', ''),
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
| `staff_middleware` | `['web', 'auth']` | Applied to all staff routes; `sfp.role` is always appended automatically |

## Migrations & Seeders

```bash
# Run migrations
php artisan migrate

# Seed all default data (or include in host app DatabaseSeeder)
php artisan db:seed --class=Dcplibrary\\Sfp\\Database\\Seeders\\SfpDatabaseSeeder

# Or simply:
php artisan db:seed
```

The host app's `DatabaseSeeder` should call `SfpDatabaseSeeder` directly:

```php
$this->call(\Dcplibrary\Sfp\Database\Seeders\SfpDatabaseSeeder::class);
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
php artisan config:clear
```

## Running Tests

```bash
./vendor/bin/phpunit --testsuite Unit
```

Tests use PHPUnit 11 with Mockery. No database or full Laravel container is required for the unit suite. A `tests/bootstrap.php` file provides a minimal stub for `Illuminate\Foundation\Auth\User` so the package can be tested without `laravel/framework` installed as a dev dependency.

## Deployment Workflow (Development)

Changes made in the worktree (`sfp/.claude/worktrees/{name}`) must be merged to the main `sfp` repo for `sfp-laravel` to pick them up (the vendor symlink points to the main repo, not the worktree):

```bash
# In the main sfp repo
git merge claude/{worktree-name} --no-edit

# In sfp-laravel
php artisan view:clear && php artisan route:clear && php artisan config:clear
```
