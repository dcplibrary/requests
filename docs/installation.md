# Installation

[← Back to README](README.md)

## Requirements

- PHP ^8.3
- Laravel ^12.0
- Livewire ^3.0 or ^4.0
- `blashbrook/papiclient` (Polaris ILS client)
- `dcplibrary/entra-sso` ^1.6 (Azure Entra ID / OIDC authentication)

## Package Setup

### Production / Staging

Require the package from GitHub using a VCS repository in the host app's `composer.json`:

```json
"repositories": {
    "dcplibrary-requests": {
        "type": "vcs",
        "url": "https://github.com/dcplibrary/requests.git"
    }
},
"require": {
    "dcplibrary/requests": "dev-main"
}
```

Then run:

```bash
composer require dcplibrary/requests:dev-main
```

## Optional: Enable Livewire Blaze (recommended)

This package can automatically optimize its **anonymous Blade components** when the host app installs `livewire/blaze`.

In the host app:

```bash
composer require livewire/blaze:^1.0
php artisan view:clear
```

No further configuration is required in the host app or in this package.

### Local Development

The package is loaded via a Composer path repository so changes are reflected immediately (via symlink) without a `composer update`:

```json
"repositories": {
    "0": {
        "type": "path",
        "url": "../requests"
    }
}
```

The vendor symlink points to the package root:

```
sfp-laravel/vendor/dcplibrary/requests → ../../../requests/
```

> **Note:** The symlink points to the **main** `requests` repo, not any active worktree. Changes in a worktree must be merged to `main` before `sfp-laravel` picks them up. See [Deployment Workflow](#deployment-workflow-development) below.

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
3. The package `request.role` middleware runs on every staff route. If the user has no `sfp_users` record but their `users.role` is `admin` or `selector`, a `sfp_users` record is **auto-provisioned** (active, same role).
4. Users with any other role (or no Entra group match) see the no-access page.

Admins can then manage the user's selector group assignments via **Settings → Users**.

## Config File

Publish the config:

```bash
php artisan vendor:publish --tag=requests-config
```

Or manually create `config/requests.php` in the host app:

```php
return [
    'route_prefix'    => env('SFP_ROUTE_PREFIX', 'request'),
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
| `staff_middleware` | `['web', 'auth']` | Applied to all staff routes; `request.role` is always appended automatically |

## Migrations & Seeders

```bash
# Run migrations
php artisan migrate

# Seed all default data (or include in host app DatabaseSeeder)
php artisan db:seed --class=Dcplibrary\\Requests\\Database\\Seeders\\RequestsDatabaseSeeder

# Or simply:
php artisan db:seed

# Seed only missing settings (never overwrites existing values). Safe to run anytime:
php artisan db:seed --class=Dcplibrary\\Requests\\Database\\Seeders\\DefaultSettingsSeeder
```

The host app's `DatabaseSeeder` should call `RequestsDatabaseSeeder` directly:

```php
$this->call(\Dcplibrary\Requests\Database\Seeders\RequestsDatabaseSeeder::class);
```

To publish migrations and seeders to the host app (for customization):

```bash
php artisan vendor:publish --tag=requests-migrations
php artisan vendor:publish --tag=requests-seeders
```

## Cache Clearing After Updates

After pulling package changes:

```bash
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

## Running Tests

Install dev dependencies first:

```bash
composer install
```

Then run the unit suite:

```bash
./vendor/bin/phpunit --testsuite Unit
```

Or run with coverage output:

```bash
./vendor/bin/phpunit --testsuite Unit --coverage-text
```

**What's covered (113 tests, 244 assertions):**

| Test File | Covers |
|-----------|--------|
| `RequireSfpRoleTest` | Role/active gate, host-app provisioning, case sensitivity, exactly 2 allowed roles |
| `CatalogFormatLabelTest` | All 17 BiblioCommons format codes, unknown code fallback, map structure |
| `SfpUserTest` | `isAdmin()`, `isSelector()`, mutual exclusivity, `accessibleIds` via Mockery |
| `MaterialScopeVisibleToTest` | Null user, admin pass-through, selector `whereIn`, no audience constraint |
| `SfpRequestScopeVisibleToTest` | Null user, admin pass-through, `where()` closure grouping both `material_type_id` + `audience_id` |
| `BibliocommonsServiceTest` | Query building, author parsing, API response parsing |
| `IsbnDbServiceTest` | ISBNdb API response parsing |

No database or full Laravel container is required for the unit suite. A `tests/bootstrap.php` file provides a minimal stub for `Illuminate\Foundation\Auth\User` so the package can be tested without `laravel/framework` installed as a dev dependency.

## Deployment Workflow (Development)

Changes made in the worktree (`requests/.claude/worktrees/{name}`) must be merged to the main `requests` repo for `sfp-laravel` to pick them up (the vendor symlink points to the main repo, not the worktree):

```bash
# In the main requests repo
git merge claude/{worktree-name} --no-edit

# In sfp-laravel
php artisan view:clear && php artisan route:clear && php artisan config:clear
```
