# dcplibrary/requests

A Laravel package for managing patron purchase requests (SFP) and interlibrary loan (ILL) requests at the Daviess County Public Library. Provides multi-step Livewire patron forms, a role-based staff admin interface, configurable form fields, Polaris ILS integration, Bibliocommons catalog scraping, and ISBNdb enrichment.

---

## Requirements

- PHP 8.3+
- Laravel 12
- Livewire 3+
- Redis (queue driver for Polaris patron lookups)
- `blashbrook/papiclient` (Polaris PAPI)
- `dcplibrary/entra-sso` ^1.6 (Microsoft Entra SSO for staff)

---

## Installation

Since this is a private package, add it to your consuming app's `composer.json` as a path or VCS repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../requests"
        }
    ],
    "require": {
        "dcplibrary/requests": "@dev"
    }
}
```

Then install:

```bash
composer require dcplibrary/requests
```

## Optional: Enable Livewire Blaze (recommended)

If the host app installs `livewire/blaze`, this package will automatically optimize its anonymous Blade components for faster rendering (especially on Livewire re-renders).

```bash
composer require livewire/blaze:^1.0
php artisan view:clear
```

The service provider (`Dcplibrary\Requests\RequestsServiceProvider`) is auto-discovered via the `extra.laravel.providers` key in `composer.json`.

---

## Publishing Assets

```bash
# Publish everything at once
php artisan vendor:publish --tag=requests

# Or individually:
php artisan vendor:publish --tag=requests-config      # config/requests.php
php artisan vendor:publish --tag=requests-migrations  # database/migrations/
php artisan vendor:publish --tag=requests-seeders     # database/seeders/
php artisan vendor:publish --tag=requests-views       # resources/views/vendor/requests/ (only if customizing)
```

CSS is served directly from inside the package via `/{prefix}/assets/css` — no `vendor:publish` needed for stylesheets.

---

## Configuration

After publishing, edit `config/requests.php`:

```php
'route_prefix' => 'request',       // Patron forms at /request/sfp and /request/ill, staff at /request/staff
'middleware' => ['web'],            // Public patron routes
'staff_middleware' => ['web', 'auth'],  // Staff admin routes
'guard' => null,                    // Optional dedicated auth guard
'isbndb' => [
    'key' => env('ISBNDB_API_KEY'),
],
'queue' => [
    'connection' => env('REQUESTS_QUEUE_CONNECTION', 'redis'),
    'name'       => env('REQUESTS_QUEUE_NAME', 'default'),
],
```

Add to `.env`:

```env
REQUESTS_ROUTE_PREFIX=request

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

# Microsoft Entra SSO
ENTRA_CLIENT_ID=
ENTRA_CLIENT_SECRET=
ENTRA_TENANT_ID=
ENTRA_REDIRECT_URI="${APP_URL}/request/auth/callback"
```

---

## Migrate and Seed

```bash
php artisan migrate
php artisan db:seed --class="Dcplibrary\Requests\Database\Seeders\RequestsDatabaseSeeder"
```

The seeder inserts default settings (rate limits, ILL thresholds, messages, catalog/ISBNdb toggles), request statuses, and an ILL selector group.

---

## Queue Worker

Required for Polaris patron lookups:

```bash
php artisan queue:work --queue=default
```

---

## Routes

All routes are registered under your configured prefix (default: `request`).

### Public (Patron)

- `GET /{prefix}/sfp` — Patron SFP form (Livewire)
- `GET /{prefix}/ill` — Patron ILL form (Livewire)
- `GET /{prefix}/my-requests` — Patron request history (PIN authentication)

### Staff Admin (`/{prefix}/staff/*`)

- `GET /requests` — Request list
- `GET /requests/{id}` — Request detail
- `PATCH /requests/{id}/status` — Update request status
- `POST /requests/{id}/catalog-recheck` — Re-check catalog for a request
- `POST /requests/{id}/convert-kind` — Convert between SFP/ILL
- `POST /requests/{id}/claim` / `POST /requests/{id}/assign` — Claim/assign to selector
- `DELETE /requests/{id}` — Delete request
- CRUD: `patrons`, `titles`, `statuses`, `users`, `groups`, `patron-status-templates`
- `GET /settings` — Admin settings
- `GET /settings/form-fields` — Form field configuration
- `GET /settings/custom-fields` — Custom field configuration
- `GET /settings/notifications` — Email notification settings
- `GET /catalog` — Catalog search settings + format label management
- `GET /backups` — Database/config backup and restore

---

## Staff Authentication

The package does **not** ship login/logout routes. Protect staff routes using the host application's authentication by configuring `requests.staff_middleware` in `config/requests.php` (default: `['web', 'auth']`).

When the authenticated user is **not** the package `Dcplibrary\Requests\Models\User`, the package maps the staff user to `staff_users` by **email** for authorization scoping and audit logging.

---

## Architecture

### Patron Forms
- `Dcplibrary\Requests\Livewire\RequestForm` — Multi-step SFP form (patron info → material details → catalog/ISBNdb match resolution → confirmation)
- `Dcplibrary\Requests\Livewire\IllForm` — Multi-step ILL form
- `Dcplibrary\Requests\Livewire\PatronRequests` — Patron request history (PIN-authenticated)

### Staff Interface (`/{prefix}/staff/*`)
Role-based access via the `request.role` middleware:
- **Admin**: full access + settings + CRUD for all lookup tables
- **Selector**: scoped to requests matching their selector group

### Key Models

- `Patron` (`patrons`) — Barcode-unique; Polaris verification fields
- `Material` (`materials`) — Deduplicated by title+author; ISBNdb enrichment
- `PatronRequest` (`requests`) — Core transaction; tracks search attempts and status
- `RequestStatus` (`request_statuses`) — Seeded; admin-manageable
- `RequestStatusHistory` (`request_status_history`) — Full audit trail
- `Setting` (`settings`) — All business rules; cached; admin UI
- `User` (`staff_users`) — Staff only; Entra SSO; role + selector group
- `SelectorGroup` (`selector_groups`) — Scopes selector access
- `Form` (`forms`) — SFP and ILL form definitions
- `Field` (`fields`) — Configurable form fields
- `FieldOption` (`field_options`) — Options for select/radio fields
- `FormFieldConfig` (`form_field_config`) — Per-form field visibility/order/labels
- `FormFieldOptionOverride` (`form_field_option_overrides`) — Per-form option overrides
- `RequestFieldValue` (`request_field_values`) — Submitted field values per request
- `CatalogFormatLabel` (`catalog_format_labels`) — Bibliocommons format label mappings
- `PatronStatusTemplate` (`patron_status_templates`) — Reusable patron email templates

### Configurable Settings (admin UI at `/{prefix}/staff/settings`)

**Request Limits:**
- `sfp_limit_count` (default: 5) — Max SFP requests per window
- `sfp_limit_window_type` (default: rolling) — rolling / calendar_month / calendar_week
- `sfp_limit_window_days` (default: 30) — Rolling window length in days
- `sfp_limit_calendar_reset_day` (default: 1) — Monthly reset day (1–28)
- `ill_limit_count` (default: unlimited) — Max ILL requests per window
- `ill_limit_window_type` / `ill_limit_window_days` / `ill_limit_calendar_reset_day` — ILL equivalents

**ILL:**
- `ill_age_threshold_days` (default: 730) — Days before ILL soft warning
- `ill_warning_message` — HTML shown when item exceeds age threshold
- `ill_isbndb_enabled` (default: true) — ISBNdb enrichment for ILL

**Messaging:**
- `duplicate_request_message` — Shown when item already requested
- `duplicate_self_request_message` — Shown when patron re-requests their own item
- `submission_success_message` — Shown on confirmation step
- `catalog_owned_message` — Shown when item is already in the catalog

**Catalog:**
- `catalog_search_enabled` (default: true) — Toggle Bibliocommons search
- `catalog_library_slug` (default: dcpl) — Bibliocommons library identifier
- `syndetics_client` (default: davia) — Syndetics client ID for book covers

---

## Bibliocommons Scraping

`BibliocommonsService` scrapes Bibliocommons HTML using `DOMXPath` (no public API). The library slug is stored in settings and is editable by admins without a code deploy.

---

## ISBNdb

Uses ISBNdb API v2. Set `ISBNDB_API_KEY` in `.env`. If not configured, ISBNdb search silently skips. Docs: https://isbndb.com/isbndb-api-documentation-v2
