# Models

[← Back to README](README.md)

All models live in `src/Models/` under the `Dcplibrary\Sfp\Models` namespace.

---

## Setting

**Table:** `settings`

Dynamic configuration store with 1-hour cache per key.

### Static Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `get` | `get(string $key, mixed $default = null): mixed` | Fetch a value; casts based on `type` (boolean → bool, integer → int, others → string) |
| `set` | `set(string $key, mixed $value): void` | Update value and bust cache |
| `allGrouped` | `allGrouped(): Collection` | All settings grouped by their `group` field |

Cache key format: `setting:{key}`. TTL: 3600 seconds.

---

## User

**Table:** `sfp_users` | Extends `Illuminate\Foundation\Auth\User`

Staff accounts authenticated via Azure Entra ID.

### Relationships

| Method | Type | Target |
|--------|------|--------|
| `selectorGroups()` | BelongsToMany | `SelectorGroup` via `selector_group_user` |
| `statusHistories()` | HasMany | `RequestStatusHistory` |

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isAdmin()` | `bool` | `role === 'admin'` |
| `isSelector()` | `bool` | `role === 'selector'` |
| `accessibleMaterialTypeIds()` | `array` | All material type IDs for admins; group-scoped IDs for selectors |
| `accessibleAudienceIds()` | `array` | All audience IDs for admins; group-scoped IDs for selectors |

---

## Patron

**Table:** `patrons`

Library patrons identified by barcode. Enriched asynchronously from Polaris after first submission.

### Relationships

| Method | Type | Target |
|--------|------|--------|
| `requests()` | HasMany | `SfpRequest` |
| `ignoredDuplicates()` | BelongsToMany | `Patron` (self) via `patron_ignored_duplicates` |

### Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `full_name` | `string` | `"{name_first} {name_last}"` |
| `effective_phone` | `string\|null` | Polaris phone or submitted phone, based on `preferred_phone` |
| `effective_email` | `string\|null` | Polaris email or submitted email, based on `preferred_email` |

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `recentRequestCount()` | `int` | Requests within the `sfp_limit_window_days` setting window |
| `hasReachedLimit()` | `bool` | Whether count ≥ `sfp_limit_count` setting |
| `applyPolarisData(array)` | `void` | Store Polaris lookup results and compute match flags |
| `markPolarisNotFound()` | `void` | Mark lookup attempted but patron not found in ILS |

---

## SfpRequest

**Table:** `requests`

The core request record. Stores both raw patron-submitted data and resolved material/search outcomes.

### Relationships

| Method | Type | Target |
|--------|------|--------|
| `patron()` | BelongsTo | `Patron` |
| `material()` | BelongsTo | `Material` |
| `audience()` | BelongsTo | `Audience` |
| `materialType()` | BelongsTo | `MaterialType` |
| `status()` | BelongsTo | `RequestStatus` (FK: `request_status_id`) |
| `statusHistory()` | HasMany | `RequestStatusHistory` ordered by `created_at` |
| `duplicateOf()` | BelongsTo | `SfpRequest` (self, FK: `duplicate_of_request_id`) |

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `transitionStatus(int $statusId, ?int $userId, ?string $note)` | `void` | Update status and create a history entry |
| `scopeVisibleTo(Builder, ?Authenticatable)` | `Builder` | Filter to requests the user is authorized to see (see [authorization.md](authorization.md)) |

---

## Material

**Table:** `materials`

Bibliographic records. Can be patron-submitted (`source=submitted`), ISBNdb-enriched (`source=isbndb`), or ILS-sourced (`source=polaris`).

### Relationships

| Method | Type | Target |
|--------|------|--------|
| `materialType()` | BelongsTo | `MaterialType` |
| `requests()` | HasMany | `SfpRequest` |

### Static Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `findMatch(string $title, string $author)` | `?self` | Case-insensitive match by normalized title and author |
| `yearExceedsIllThreshold(?string $year)` | `bool` | Whether the year is older than `ill_age_threshold_days` setting |

### Instance Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isOlderThanIllThreshold()` | `bool` | Check using `exact_publish_date` or numeric `publish_date` |

---

## MaterialType

**Table:** `material_types`

### Relationships

`materials()` HasMany, `requests()` HasMany, `selectorGroups()` BelongsToMany

### Scope

`scopeActive()` — where `active = true`, ordered by `sort_order`

---

## Audience

**Table:** `audiences`

### Relationships

`requests()` HasMany, `selectorGroups()` BelongsToMany

### Scope

`scopeActive()` — where `active = true`, ordered by `sort_order`

---

## RequestStatus

**Table:** `request_statuses`

### Relationships

`requests()` HasMany (FK: `request_status_id`), `history()` HasMany

### Scope

`scopeActive()` — where `active = true`, ordered by `sort_order`

---

## RequestStatusHistory

**Table:** `request_status_history`

Audit log. `user_id` is null for system-initiated transitions.

### Relationships

`request()` BelongsTo `SfpRequest`, `status()` BelongsTo `RequestStatus`, `user()` BelongsTo `User`

---

## SelectorGroup

**Table:** `selector_groups`

### Relationships

`users()` BelongsToMany, `materialTypes()` BelongsToMany, `audiences()` BelongsToMany

### Scope

`scopeActive()` — where `active = true`

---

## CatalogFormatLabel

**Table:** `catalog_format_labels`

### Static Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `map()` | `array` | `['BK' => 'Book', 'EBOOK' => 'eBook', ...]` — keyed by format_code |
