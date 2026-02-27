# dcplibrary/sfp — Developer Documentation

Suggest for Purchase (SFP) is a Laravel package that provides a patron-facing request form and a staff admin interface for DC Public Library's collection development workflow.

## Table of Contents

| Page | Contents |
|------|----------|
| [Installation](installation.md) | Requirements, setup, config, seeders, Entra SSO |
| [Architecture](architecture.md) | Package structure, service provider, routing overview |
| [Database](database.md) | Full schema: all tables, columns, relationships, indexes |
| [Models](models.md) | All Eloquent models: properties, relationships, methods |
| [Services](services.md) | BibliocommonsService, IsbnDbService, CoverService, PatronService |
| [Controllers](controllers.md) | All admin controllers and their actions |
| [Livewire Form](livewire-form.md) | SfpForm component: state, workflow, steps |
| [Settings](settings.md) | All configurable settings keys and groups |
| [Authorization](authorization.md) | Roles, middleware, selector groups, scopeVisibleTo |
| [Jobs](jobs.md) | Background jobs: Polaris lookup |

## Quick Reference

```
Patron form:   GET  /sfp
Staff area:    GET  /sfp/staff/requests
Titles:        GET  /sfp/staff/titles
Catalog cfg:   GET  /sfp/staff/catalog
Settings:      GET  /sfp/staff/settings
```

**Package namespace:** `Dcplibrary\Sfp`
**View namespace:** `sfp::`
**Blade component prefix:** `<x-sfp::logo />`
**Livewire component:** `sfp-form`

## Roles

| Role | Access |
|------|--------|
| `admin` | Full access to all requests, titles, patrons, settings |
| `selector` | Requests and titles scoped to their SelectorGroup material types and audiences |

Users with any other role (or no `sfp_users` record and no matching Entra group) see the **no-access page** instead of the staff UI. See [Authorization](authorization.md) for the full middleware resolution order.

## Running Tests

```bash
./vendor/bin/phpunit --testsuite Unit
```

113 unit tests covering role gates, visibility scopes, format label mapping, and user model helpers. No database or Laravel container required.
