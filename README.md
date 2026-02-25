# DCPL Suggest for Purchase (SFP) Application

A Laravel 12 + Livewire 4 application for managing patron suggest-for-purchase requests at DC Public Library.

---

## Stack

- **PHP 8.3+** / **Laravel 12**
- **Livewire 4** (patron-facing multi-step form + future staff components)
- **Alpine.js** (lightweight interactivity)
- **Tailwind CSS** (ADA-compliant styling, focus management)
- **MySQL / PostgreSQL** (your choice)
- **Redis** (queue driver + cache for post-submit processing)
- **blashbrook/papiclient** (Polaris PAPI integration)
- **blashbrook/entra-sso** (staff authentication via Microsoft Entra)

---

## Setup

### 1. Install dependencies

```bash
composer install
npm install && npm run build
```

### 2. Environment

Copy `.env.example` to `.env` and configure:

```env
# App
APP_URL=https://sfp.dcplibrary.org

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=sfp
DB_USERNAME=
DB_PASSWORD=

# Queue (use Redis for production)
QUEUE_CONNECTION=redis
CACHE_STORE=redis

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

# ISBNdb
ISBNDB_API_KEY=

# Microsoft Entra SSO (blashbrook/entra-sso)
ENTRA_CLIENT_ID=
ENTRA_CLIENT_SECRET=
ENTRA_TENANT_ID=
ENTRA_REDIRECT_URI="${APP_URL}/auth/callback"
```

### 3. Database

```bash
php artisan migrate
php artisan db:seed
```

### 4. Queue worker (required for Polaris lookups)

```bash
php artisan queue:work --queue=default
```

---

## Architecture

### Public-facing
- **`/`** — Livewire SFP form (`App\Livewire\SfpForm`)
  - Step 1: Patron information (barcode, name, phone, email)
  - Step 2: Material details (type, audience, title, author, date, where heard, ILL checkbox)
  - Step 3: Catalog + ISBNdb match resolution (interactive)
  - Step 4: Confirmation

### Post-submit processing (queued jobs)
1. `LookupPatronInPolaris` — verifies patron in Polaris, stores match data
2. Catalog (Bibliocommons) and ISBNdb searches run synchronously during submit for interactive patron flow

### Staff-facing (`/staff/*`)
- Requires Microsoft Entra SSO login
- Roles: `admin`, `selector`
- Selectors scoped to requests by material type + audience via selector groups
- Admin has full access including settings, CRUD for lookup tables, user management

### Key models
| Model | Table | Notes |
|---|---|---|
| `Patron` | `patrons` | Barcode-unique, Polaris verification fields |
| `Material` | `materials` | Deduplicated by title+author; enriched by ISBNdb |
| `SfpRequest` | `requests` | Core transaction; tracks catalog/ISBNdb search attempts |
| `RequestStatus` | `request_statuses` | Seeded; admin-manageable |
| `RequestStatusHistory` | `request_status_history` | Full audit trail with user + note |
| `Setting` | `settings` | All app rules; cached; admin UI |

### Configurable settings (admin UI at `/staff/settings`)
| Key | Default | Description |
|---|---|---|
| `sfp_limit_count` | 5 | Requests per window |
| `sfp_limit_window` | day | day / week / month |
| `ill_age_threshold_years` | 2 | Years before ILL soft warning |
| `catalog_search_enabled` | true | Toggle Bibliocommons search |
| `isbndb_search_enabled` | true | Toggle ISBNdb enrichment |
| `duplicate_request_message` | (text) | Shown when item already requested |
| `submission_success_message` | (text) | Shown on confirmation step |
| `post_submit_mode` | wait | wait or email |

---

## Dev tooling

Install [Laravel Boost](https://boost.laravel.com) for AI-assisted development:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

This gives your AI editor (Cursor, VS Code, etc.) live access to your schema, routes, logs, and config while you build.

---

## Entra SSO

The `EntraAuthController` is stubbed. Wire it up using `blashbrook/entra-sso` per its docs, or use `socialiteproviders/microsoft-azure` with `Laravel\Socialite`. The controller comments show the Socialite pattern.

---

## Bibliocommons scraping

`BibliocommonsService` scrapes the Bibliocommons HTML response using `DOMXPath`. This is resilient to minor markup changes but may require updates if Bibliocommons significantly restructures their results page. The URL template is stored in settings and is editable by admins without a code deploy.

---

## ISBNdb

Uses ISBNdb API v2. Set `ISBNDB_API_KEY` in `.env`. Docs: https://isbndb.com/isbndb-api-documentation-v2
# sfp
