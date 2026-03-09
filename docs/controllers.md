# Controllers

[← Back to README](README.md)

All controllers live in `src/Http/Controllers/Admin/` and extend `Dcplibrary\Sfp\Http\Controllers\Controller`. All routes are under the `request.staff.*` name prefix.

---

## Route Summary

```
GET    /{prefix}/staff/requests                          request.staff.requests.index
GET    /{prefix}/staff/requests/{id}                     request.staff.requests.show
PATCH  /{prefix}/staff/requests/{id}/status              request.staff.requests.status
POST   /{prefix}/staff/requests/{id}/catalog-recheck     request.staff.requests.catalog-recheck

GET    /{prefix}/staff/patrons                           request.staff.patrons.index
GET    /{prefix}/staff/patrons/{id}                      request.staff.patrons.show
GET    /{prefix}/staff/patrons/{id}/edit                 request.staff.patrons.edit
PATCH  /{prefix}/staff/patrons/{id}                      request.staff.patrons.update
GET    /{prefix}/staff/patrons/{id}/merge-confirm        request.staff.patrons.merge-confirm
POST   /{prefix}/staff/patrons/{id}/merge                request.staff.patrons.merge
POST   /{prefix}/staff/patrons/{id}/retrigger-polaris    request.staff.patrons.retrigger-polaris
POST   /{prefix}/staff/patrons/{id}/ignore-duplicate     request.staff.patrons.ignore-duplicate

GET    /{prefix}/staff/titles                            request.staff.titles.index
GET    /{prefix}/staff/titles/{id}                       request.staff.titles.show
POST   /{prefix}/staff/titles/{id}/merge                 request.staff.titles.merge
POST   /{prefix}/staff/titles/{id}/bulk-status           request.staff.titles.bulk-status

GET    /{prefix}/staff/settings                          request.staff.settings.index
PATCH  /{prefix}/staff/settings                          request.staff.settings.update

GET    /{prefix}/staff/catalog                           request.staff.catalog.index
PATCH  /{prefix}/staff/catalog                           request.staff.catalog.update
POST   /{prefix}/staff/catalog/format-labels             request.staff.catalog.format-labels.store
DELETE /{prefix}/staff/catalog/format-labels/{id}        request.staff.catalog.format-labels.destroy

# Standard resource routes (create/edit/store/update/destroy) for:
/{prefix}/staff/material-types    request.staff.material-types.*
/{prefix}/staff/audiences         request.staff.audiences.*
/{prefix}/staff/statuses          request.staff.statuses.*
/{prefix}/staff/users             request.staff.users.*  (no create/store)
/{prefix}/staff/groups            request.staff.groups.*
```

---

## RequestController

### `index(Request $request)`

Paginates requests (30/page) with filters:

| Query param | Filters by |
|-------------|-----------|
| `status` | `request_status_id` |
| `material_type` | `material_type_id` |
| `audience` | `audience_id` |
| `search` | Submitted title, author, patron barcode, patron name |

Respects `scopeVisibleTo(auth()->user())` — see [authorization.md](authorization.md).

### `show(SfpRequest $sfpRequest)`

Loads request with: `patron`, `material`, `materialType`, `audience`, `status`, `statusHistory.status`, `statusHistory.user`, `duplicateOf`.

### `updateStatus(Request, SfpRequest)`

Validates `status_id` (exists in `request_statuses`) and optional `note` (max 1000 chars). Calls `$sfpRequest->transitionStatus()`.

### `recheckCatalog(SfpRequest)`

Re-runs catalog search for an existing request. Prefers format preference: `BK` first, then `LPRINT`, then the first result. Updates `catalog_searched`, `catalog_result_count`, and optionally `catalog_match_bib_id`.

---

## PatronController

### `index(Request $request)`

Paginates patrons (30/page). Default view shows **flagged patrons** (unlooked-up, mismatches, or those with suspected duplicates). Pass `?show_all=1` to show all.

A patron is flagged if:
- `polaris_lookup_attempted = false`
- Any match field is `false` (name, phone, or email mismatch)
- Has suspected duplicates (same normalized phone+lastname, email, or Polaris ID)

### `show(Patron $patron)`

Loads patron with requests and computes suspected duplicate patron IDs using:
- Same normalized last name + phone (digits only)
- Same email (case-insensitive)
- Same `polaris_patron_id`

Excludes pairs already in `patron_ignored_duplicates`.

### `merge(Request, Patron $loser)`

Runs in a database transaction:
1. Validates `winner_id`
2. Applies `preferred_phone` / `preferred_email` overrides from the form to the winner
3. Optionally overrides `polaris_patron_id` on the winner
4. Reassigns all of `$loser->requests` to the winner
5. Deletes the loser patron

### `ignoreDuplicate(Request, Patron $patron)`

Inserts **both directions** into `patron_ignored_duplicates`:
- `(patron_id, ignored_patron_id)` and `(ignored_patron_id, patron_id)`

### `retriggerPolaris(Patron $patron)`

Resets `polaris_lookup_attempted = false` and re-dispatches `LookupPatronInPolaris`.

---

## TitleController

### `index(Request $request)`

Lists materials ordered by request count (desc), then title. Supports `?search=` and `?source=` filters. Identifies duplicate candidates (same normalized title+author across different material IDs).

### `merge(Request, Material $loser)`

Runs in a database transaction: reassigns all `$loser->requests` to the winner, deletes the loser.

### `bulkStatus(Request, Material $material)`

Updates all requests linked to this material to a new `status_id` in one query, logging history for each.

---

## SettingController

### `index()`

Displays all settings via `Setting::allGrouped()`.

### `update(Request)`

Accepts `settings[]` array with `key` and `value`. Validates each `key` exists in the `settings` table. Calls `Setting::set()` for each.

---

## CatalogController

### `index()`

Loads settings from groups `catalog`, `syndetics`, `isbndb` only (via `Setting::allGrouped()->only([...])`). Also loads all `CatalogFormatLabel` rows ordered by `format_code`.

### `update(Request)`

In one request, updates both settings and format labels:
- `settings[]` — same as `SettingController::update`
- `format_labels[]` — array of `{id, label}`; updates each by ID

### `storeFormatLabel(Request)`

Validates `format_code` is unique and creates a new `CatalogFormatLabel`. The `format_code` field uses `uppercase` CSS transform in the view.

### `destroyFormatLabel(CatalogFormatLabel $catalogFormatLabel)`

Deletes the row. No guard (format labels can always be deleted).

---

## MaterialTypeController / AudienceController / RequestStatusController

Standard CRUD. All three:
- Auto-generate `slug` via `Str::slug($data['name'])` on create
- Guard `destroy()`: return error if any requests reference the record

`AudienceController` also validates `bibliocommons_value` (required, max 50).
`RequestStatusController` also validates `color` (nullable, max 20) and `is_terminal` (boolean).

---

## UserController

- `index()` — all users ordered by name
- `edit(User)` / `update(Request, User)` — update `role`, `active`, and sync `selectorGroups` (many-to-many)
- `destroy(User)` — guard: cannot delete the currently authenticated user

---

## SelectorGroupController

Standard CRUD. `store()` and `update()` sync `material_types` and `audiences` many-to-many relations after save.
