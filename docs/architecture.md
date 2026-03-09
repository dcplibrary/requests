# Architecture

[← Back to README](README.md)

## Package Structure

```
dcplibrary/sfp/
├── config/
│   └── sfp.php                        Package config defaults
├── database/
│   ├── migrations/                    11 migration files (ordered 000001–000011)
│   └── seeders/                       7 seeders + master SfpDatabaseSeeder
├── docs/                              Developer documentation (this folder)
├── resources/
│   └── views/
│       ├── components/
│       │   └── logo.blade.php         DCPL logo anonymous Blade component
│       ├── layouts/
│       │   └── sfp.blade.php          Patron form base layout
│       ├── livewire/
│       │   └── sfp-form.blade.php     4-step patron request form
│       └── staff/                     Staff admin UI views
│           ├── _layout.blade.php      Staff nav + auth layout
│           ├── settings/
│           │   ├── _layout.blade.php  Settings sidebar layout
│           │   └── index.blade.php    General settings form
│           ├── catalog/
│           │   └── index.blade.php    Catalog settings + format labels
│           ├── requests/
│           ├── patrons/
│           ├── titles/
│           ├── material-types/
│           ├── audiences/
│           ├── statuses/
│           ├── users/
│           └── groups/
└── src/
    ├── SfpServiceProvider.php
    ├── Http/Controllers/Admin/        10 staff controllers
    ├── Jobs/                          LookupPatronInPolaris, ProcessSfpRequest
    ├── Livewire/
    │   └── SfpForm.php                Main patron-facing Livewire component
    ├── Models/                        11 Eloquent models
    ├── routes/
    │   └── web.php                    All routes (public + staff)
    └── Services/                      4 service classes
```

## Service Provider

`SfpServiceProvider` bootstraps the package in `boot()`:

1. **Routes** — loaded from `src/routes/web.php`
2. **Views** — registered under the `sfp` namespace; anonymous components registered at `sfp::components`
3. **Livewire** — `sfp-form` component registered
4. **Migrations** — loaded from `database/migrations` (console-only)
5. **Publishables** — four publish tags: `sfp-config`, `sfp-migrations`, `sfp-seeders`, `sfp-views` (plus the umbrella `sfp` tag)

## Routing

All routes share the configured prefix (default: `/sfp`).

| Visibility | Middleware config key | Default middleware |
|-----------|----------------------|-------------------|
| Patron form | `middleware` | `['web']` |
| Staff area | `staff_middleware` | `['web', 'auth']` |

See [installation.md](installation.md) for config details.

Full route list: see [controllers.md](controllers.md).

## External Dependencies

| Dependency | Purpose |
|-----------|---------|
| `blashbrook/papiclient` | Polaris ILS PAPI client for patron lookup |
| BiblioCommons gateway API | Internal JSON API used for catalog search |
| ISBNdb v2 API | Fallback bibliographic data source |
| Syndetics | Book cover images |
| Azure Entra ID | Staff authentication (OIDC) |

## Blade Components

The DCPL logo is an anonymous Blade component registered via:

```php
Blade::anonymousComponentNamespace('sfp::components', 'sfp');
```

Usage:

```blade
{{-- Default link to url('/') --}}
<x-sfp::logo />

{{-- Custom href --}}
<x-sfp::logo :href="route('request.staff.requests.index')" />
```

The component renders the 4-panel DCPL SVG logo alongside `config('app.name')`.
