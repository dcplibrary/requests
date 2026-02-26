# dcplibrary/sfp — Developer Documentation

Suggest for Purchase (SFP) is a Laravel package that provides a patron-facing request form and a staff admin interface for DC Public Library's collection development workflow.

## Table of Contents

| Page | Contents |
|------|----------|
| [Installation](installation.md) | Requirements, setup, config, seeders |
| [Architecture](architecture.md) | Package structure, service provider, routing overview |
| [Database](database.md) | Full schema: all tables, columns, relationships, indexes |
| [Models](models.md) | All Eloquent models: properties, relationships, methods |
| [Services](services.md) | BibliocommonsService, IsbnDbService, CoverService, PatronService |
| [Controllers](controllers.md) | All admin controllers and their actions |
| [Livewire Form](livewire-form.md) | SfpForm component: state, workflow, steps |
| [Settings](settings.md) | All configurable settings keys and groups |
| [Authorization](authorization.md) | Roles, selector groups, scopeVisibleTo |
| [Jobs](jobs.md) | Background jobs: Polaris lookup |

## Quick Reference

```
Patron form:   GET  /sfp
Staff area:    GET  /sfp/staff/requests
Catalog cfg:   GET  /sfp/staff/catalog
Settings:      GET  /sfp/staff/settings
```

**Package namespace:** `Dcplibrary\Sfp`
**View namespace:** `sfp::`
**Blade component prefix:** `<x-sfp::logo />`
**Livewire component:** `sfp-form`
