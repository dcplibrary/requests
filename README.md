# dcplibrary/sfp

A Laravel package for managing patron Suggest-for-Purchase (SFP) requests at DC Public Library. Provides a multi-step Livewire patron form, a role-based staff admin interface, Polaris ILS integration, Bibliocommons catalog scraping, and ISBNdb enrichment.

---

## Requirements

- PHP 8.3+
- Laravel 12
- Livewire 3
- Redis (queue driver for Polaris patron lookups)
- `blashbrook/papiclient` (Polaris PAPI)

---

## Installation

Since this is a private package, add it to your consuming app's `composer.json` as a path or VCS repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../sfp"
        }
    ],
    "require": {
        "dcplibrary/sfp": "@dev"
    }
}
```

Then install:

```bash
composer require dcplibrary/sfp
```

The service provider (`Dcplibrary\Sfp\SfpServiceProvider`) is auto-discovered via the `extra.laravel.providers` key in `composer.json`.

---

## Publishing Assets

```bash
# Publish everything at once
php artisan vendor:publish --tag=sfp

# Or individually:
php artisan vendor:publish --tag=sfp-config      # config/sfp.php
php artisan vendor:publish --tag=sfp-migrations  # database/migrations/
php artisan vendor:publish --tag=sfp-seeders     # database/seeders/
php artisan vendor:publish --tag=sfp-views       # resources/views/vendor/sfp/ (only if customizing)
```

---

## Configuration

After publishing, edit `config/sfp.php`:

```php
'route_prefix' => 'sfp',   // Patron form at /sfp, staff at /sfp/staff
'isbndb' => [
    'key' => env('ISBNDB_API_KEY'),
],
```

Add to `.env`:

```env
SFP_ROUTE_PREFIX=sfp

# ISBNdb (optional — disables ISBNdb enrichment if not set)
ISBNDB_API_KEY=

# Polaris PAPI (blashbrook/papiclient)
PAPI_ACCESS_ID=
PAPI_ACCESS_KEY=
PAPI_BASE_URL=https://catalog.dcplibrary.org/PAPIService/REST
PAPI_PROTECTED_SCOPE=protected
PAPI_PUBLIC_SCOPE=public
PAPI_VERSION=v1
PAPI_LANGID=1033
PAPI_APPID=100
PAPI_ORGID=3
PAPI_LOGONBRANCHID=
PAPI_LOGONUSERID=
PAPI_LOGONWORKSTATIONID=
PAPI_DOMAIN=
PAPI_STAFF=
PAPI_PASSWORD=

# Microsoft Entra SSO (see Entra SSO section below)
ENTRA_CLIENT_ID=
ENTRA_CLIENT_SECRET=
ENTRA_TENANT_ID=
ENTRA_REDIRECT_URI="${APP_URL}/sfp/auth/callback"
```

---

## Migrate and Seed

```bash
php artisan migrate
php artisan db:seed --class="Dcplibrary\Sfp\Database\Seeders\SfpDatabaseSeeder"
```

The seeder inserts:
- Default settings (rate limits, ILL thresholds, messages, catalog/ISBNdb toggles)
- Material types: Book, Large Print, Graphic Novel, DVD, Blu-Ray, eAudiobook, eBook, Video Game, Other
- Audiences: Adult, Children, Young Adult
- Request statuses: Pending, Under Review, On Order, Purchased, Denied, ILL Referred

---

## Queue Worker

Required for Polaris patron lookups:

```bash
php artisan queue:work --queue=default
```

---

## Routes

After install, the package registers these routes under your configured prefix (default: `sfp`):

| URL | Name | Description |
|-----|------|-------------|
| `GET /{prefix}/` | `sfp.form` | Patron-facing SFP form (Livewire) |
| `GET /{prefix}/login` | `sfp.login` | Entra SSO redirect |
| `GET /{prefix}/auth/callback` | `sfp.auth.callback` | Entra SSO callback |
| `POST /{prefix}/logout` | `sfp.logout` | Log out |
| `GET /{prefix}/staff/requests` | `sfp.staff.requests.index` | Staff request list |
| `GET /{prefix}/staff/requests/{id}` | `sfp.staff.requests.show` | Request detail |
| `PATCH /{prefix}/staff/requests/{id}/status` | `sfp.staff.requests.status` | Update status |
| `GET /{prefix}/staff/settings` | `sfp.staff.settings.index` | Admin settings |
| (and CRUD routes for material-types, audiences, statuses, users, groups) | | Admin only |

---

## Entra SSO

`EntraAuthController` is stubbed. Wire up using `blashbrook/entra-sso` or `socialiteproviders/microsoft-azure`. The controller's comments show the Socialite pattern. The auth guard is `sfp`, backed by the `sfp_users` table.

Example callback implementation (Socialite):

```php
// In EntraAuthController::callback()
$entraUser = Socialite::driver('azure')->user();

$user = \Dcplibrary\Sfp\Models\User::updateOrCreate(
    ['entra_id' => $entraUser->getId()],
    ['name' => $entraUser->getName(), 'email' => $entraUser->getEmail(), 'last_login_at' => now()]
);

if (! $user->active) {
    Auth::guard(config('sfp.guard'))->logout();
    return redirect()->route('sfp.login')->withErrors(['error' => 'Account inactive.']);
}

Auth::guard(config('sfp.guard'))->login($user, true);
return redirect()->route('sfp.staff.requests.index');
```

---

## Architecture

### Patron Form
`Dcplibrary\Sfp\Livewire\SfpForm` — 4-step Livewire component:
1. Patron information (barcode, name, phone, email)
2. Material details (type, audience, title, author, date, ILL flag)
3. Catalog + ISBNdb match resolution (interactive)
4. Confirmation

### Staff Interface (`/{prefix}/staff/*`)
Role-based access via the `sfp` auth guard:
- **Admin**: full access + settings + CRUD for all lookup tables
- **Selector**: scoped to requests matching their selector group (material types + audiences)

### Key Models
| Model | Table | Notes |
|-------|-------|-------|
| `Patron` | `patrons` | Barcode-unique; Polaris verification fields |
| `Material` | `materials` | Deduplicated by title+author; enriched by ISBNdb |
| `SfpRequest` | `requests` | Core transaction; tracks search attempts |
| `RequestStatus` | `request_statuses` | Seeded; admin-manageable |
| `RequestStatusHistory` | `request_status_history` | Full audit trail |
| `Setting` | `settings` | All business rules; 1-hour cached; admin UI |
| `User` | `sfp_users` | Staff only; Entra SSO; role + selector group |
| `SelectorGroup` | `selector_groups` | Scopes selector access to material types + audiences |

### Configurable Settings (admin UI at `/{prefix}/staff/settings`)
| Key | Default | Description |
|-----|---------|-------------|
| `sfp_limit_count` | 5 | Requests per window |
| `sfp_limit_window` | day | day / week / month |
| `ill_age_threshold_years` | 2 | Years before ILL soft warning |
| `catalog_search_enabled` | true | Toggle Bibliocommons search |
| `catalog_search_url_template` | (Bibliocommons URL) | Editable search URL with tokens |
| `isbndb_search_enabled` | true | Toggle ISBNdb enrichment |
| `duplicate_request_message` | (text) | Shown when item already requested |
| `submission_success_message` | (text) | Shown on confirmation step |
| `post_submit_mode` | wait | wait or email |

---

## Bibliocommons Scraping

`BibliocommonsService` scrapes Bibliocommons HTML using `DOMXPath` (no public API). The URL template is stored in settings and is editable by admins without a code deploy.

---

## ISBNdb

Uses ISBNdb API v2. Set `ISBNDB_API_KEY` in `.env`. If not configured, ISBNdb search silently skips. Docs: https://isbndb.com/isbndb-api-documentation-v2
