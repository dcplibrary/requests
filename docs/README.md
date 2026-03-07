# dcplibrary/sfp — Developer Documentation

Suggest for Purchase (SFP) is a Laravel package that provides a patron-facing request form and a staff admin interface for the Daviess County Public Library's collection development workflow.

## Table of Contents

| Page | Contents |
|------|----------|
| [Installation](installation.md) | Requirements, [production setup](installation.md#production--staging), [local dev setup](installation.md#local-development), config, seeders, Entra SSO |
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

## API Documentation

API docs are generated from PHPDoc blocks and published via GitHub Pages:

- [API Docs (phpDocumentor)](https://dcplibrary.github.io/sfp/)

## Roles

| Role | Access |
|------|--------|
| `admin` | Full access to all requests, titles, patrons, settings |
| `selector` | Requests and titles scoped to their SelectorGroup material types and audiences |

Users with any other role (or no `sfp_users` record and no matching Entra group) see the **no-access page** instead of the staff UI. See [Authorization](authorization.md) for the full middleware resolution order.

## Running Tests

```bash
composer install
./vendor/bin/phpunit --testsuite Unit
```

113 unit tests, 244 assertions. No database or Laravel container required. See [Installation → Running Tests](installation.md#running-tests) for the full test inventory.

## Releases (semantic-release / Conventional Commits)

Releases are automated via semantic-release using the Angular/Conventional Commits preset.

- **When it runs**: on every push to `main` via `.github/workflows/release.yml`
- **What it does**:
  - Calculates the next version from commit messages
  - Updates `CHANGELOG.md`
  - Commits the changelog back to `main` as `chore(release): x.y.z [skip ci]`
  - Creates a Git tag and GitHub Release (default tag format is `vX.Y.Z`)

### Commit prefixes and release impact

| Prefix | What it covers | Release impact | Notes |
|---|---|---|---|
| `feat` | User-facing feature / new capability | **Minor** (\(x.\*\)) | Included in release notes as **Features** |
| `fix` | Bug fix | **Patch** (\(\*.x\)) | Included in **Bug Fixes** |
| `perf` | Performance improvement | **Patch** | Included in **Performance** |
| `refactor` | Internal refactor that changes code without changing behavior | **Patch** | Included in **Code Refactoring** |
| `docs` | Documentation changes | **Patch** | Included in **Documentation** |
| `test` | Tests only | **No release** | Hidden from generated release notes |
| `ci` | CI workflow/config changes | **No release** | Hidden from generated release notes |
| `chore` | Maintenance tasks (formatting, tooling, housekeeping) | **No release** | Hidden from generated release notes; semantic-release uses `chore(release): ...` for release commits |

### Breaking changes
Any commit marked as a breaking change triggers a **Major** release.

Use either:
- `feat!: ...` / `fix!: ...` (the `!`), or
- a footer containing `BREAKING CHANGE: ...`

