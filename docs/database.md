# Database Schema

[← Back to README](README.md)

Migrations run in numerical order (`000001`–`000011`). All tables use `id` (auto-increment bigint) and `timestamps` unless noted.

---

## settings

Dynamic key-value configuration store. Values are cached for 1 hour per key.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `key` | string | Unique |
| `value` | text | Stored as string; cast on read |
| `label` | string | Human-readable name for admin UI |
| `type` | string | `string` \| `integer` \| `boolean` \| `text` \| `html` |
| `group` | string | Groups settings in the UI (e.g. `catalog`, `ill`, `messaging`) |
| `description` | text | Tooltip text in admin UI |
| `timestamps` | | |

See [settings.md](settings.md) for all keys and groups.

---

## material_types

Types of library materials a patron can request.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | e.g. "Book", "eBook" |
| `slug` | string | Unique |
| `active` | boolean | Default true |
| `has_other_text` | boolean | Enables free-text input ("Other") |
| `sort_order` | integer | Display order on patron form |
| `timestamps` | | |

---

## audiences

Patron audience segments.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | e.g. "Adult" |
| `slug` | string | Unique |
| `bibliocommons_value` | string | Passed to BiblioCommons `audience:` filter |
| `active` | boolean | |
| `sort_order` | integer | |
| `timestamps` | | |

---

## request_statuses

Workflow statuses for SFP requests.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | e.g. "Pending", "Purchased" |
| `slug` | string | Unique |
| `color` | string | Hex color for badge display |
| `sort_order` | integer | |
| `active` | boolean | |
| `is_terminal` | boolean | True = resolved state (no further action needed) |
| `timestamps` | | |

**Default statuses:** Pending, Under Review, On Order, Purchased, Denied, ILL Referred

---

## sfp_users

Staff user accounts, separate from the host application's users table.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string | Unique |
| `entra_id` | string | Nullable; Azure Entra object ID |
| `role` | enum | `admin` \| `selector` |
| `active` | boolean | |
| `last_login_at` | timestamp | Nullable |
| `remember_token` | string | Hidden |
| `timestamps` | | |

---

## selector_groups + junction tables

Groups scoping selectors to specific material types and audiences.

**selector_groups**

| Column | Type |
|--------|------|
| `id` | bigint PK |
| `name` | string |
| `description` | text, nullable |
| `active` | boolean |
| `timestamps` | |

**Junction tables** (composite primary keys):

| Table | Columns |
|-------|---------|
| `selector_group_user` | `selector_group_id`, `user_id` |
| `selector_group_material_type` | `selector_group_id`, `material_type_id` |
| `selector_group_audience` | `selector_group_id`, `audience_id` |

---

## patrons

Library patrons who have submitted at least one request.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `barcode` | string | Unique; indexed |
| `name_first` | string | As submitted on form |
| `name_last` | string | |
| `phone` | string | |
| `email` | string | Nullable |
| `found_in_polaris` | boolean | Null until lookup runs |
| `polaris_lookup_attempted` | boolean | |
| `polaris_lookup_at` | timestamp | Nullable |
| `polaris_patron_id` | integer | Nullable |
| `polaris_patron_code_id` | integer | Nullable |
| `polaris_name_first` | string | Nullable |
| `polaris_name_last` | string | Nullable |
| `polaris_phone` | string | Nullable |
| `polaris_email` | string | Nullable |
| `name_first_matches` | boolean | Nullable; null until lookup runs |
| `name_last_matches` | boolean | Nullable |
| `phone_matches` | boolean | Nullable |
| `email_matches` | boolean | Nullable |
| `preferred_phone` | string | `'submitted'` (default) or `'polaris'` |
| `preferred_email` | string | `'submitted'` (default) or `'polaris'` |
| `timestamps` | | |

**patron_ignored_duplicates** (composite PK):

| Column | Type |
|--------|------|
| `patron_id` | bigint FK → patrons |
| `ignored_patron_id` | bigint FK → patrons, cascade delete |

Rows are inserted symmetrically (both directions) when staff mark two patrons as not duplicates.

---

## materials

Bibliographic records for items referenced by requests.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `title` | string | |
| `author` | string | |
| `publish_date` | string | Flexible format ("2022", "January 2022") |
| `isbn` | string | Nullable |
| `isbn13` | string | Nullable |
| `publisher` | string | Nullable |
| `exact_publish_date` | date | Nullable; cast to Carbon |
| `edition` | string | Nullable |
| `overview` | text | Nullable |
| `source` | string | `submitted` \| `isbndb` \| `polaris` |
| `material_type_id` | bigint FK | Nullable → material_types |
| `timestamps` | | |

**Indexes:** `(title, author)`, `(isbn)`

---

## requests

The core table — one row per patron suggestion.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `patron_id` | bigint FK | → patrons |
| `material_id` | bigint FK | Nullable → materials |
| `audience_id` | bigint FK | Nullable → audiences |
| `material_type_id` | bigint FK | Nullable → material_types |
| `request_status_id` | bigint FK | → request_statuses |
| `submitted_title` | string | As entered by patron |
| `submitted_author` | string | |
| `submitted_publish_date` | string | Nullable |
| `other_material_text` | string | Nullable; free-text for "Other" type |
| `where_heard` | text | Nullable |
| `ill_requested` | boolean | Patron opted into ILL fallback |
| `catalog_searched` | boolean | |
| `catalog_result_count` | integer | Nullable |
| `catalog_match_accepted` | boolean | Nullable; null = not shown results yet |
| `catalog_match_bib_id` | string | Nullable; BiblioCommons bib ID |
| `isbndb_searched` | boolean | |
| `isbndb_result_count` | integer | Nullable |
| `isbndb_match_accepted` | boolean | Nullable |
| `is_duplicate` | boolean | |
| `duplicate_of_request_id` | bigint FK | Nullable → requests (self) |
| `timestamps` | | |

**Indexes:** `patron_id`, `material_id`, `request_status_id`, `created_at`

---

## request_status_history

Audit trail for request status transitions and sent email notifications.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `request_id` | bigint FK | → requests, cascade delete |
| `request_status_id` | bigint FK | → request_statuses (context at log time) |
| `user_id` | bigint FK | Nullable → sfp_users; null = system |
| `note` | text | Nullable; for notifications, describes recipients/subject |
| `activity_type` | string | Nullable; when set, row is an email log (`staff_routing`, `patron_email`, `staff_assignee`, `staff_workflow`) |
| `timestamps` | | |

**Index:** `request_id`

---

## catalog_format_labels

Maps BiblioCommons format codes to display labels shown on the patron form.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `format_code` | string | Unique; e.g. `BK`, `EAUDIOBOOK` |
| `label` | string | e.g. `Book`, `eAudiobook` |
| `timestamps` | | |

**Default codes:** `BK`, `BOOK_CD`, `AB`, `EAUDIOBOOK`, `EBOOK`, `LPRINT`, `DVD`, `BLURAY`, `UK`, `GRAPHIC_NOVEL_DOWNLOAD`, `VIDEO_GAME`, `VIDEO_ONLINE`, `PASS`, `MAG_ONLINE`, `MAG`, `KIT`, `EQUIPMENT`

---

## forms (presentation layer)

Form definitions. Which material types and custom fields appear on each form, and their per-form label/order/required/visibility/step/conditional logic, are in the pivot tables below — not on the core data tables.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | e.g. "Interlibrary Loan", "Suggest for Purchase" |
| `slug` | string | Unique; e.g. `ill`, `sfp` |
| `timestamps` | | |

**Seeded:** `ill`, `sfp`.

---

## form_material_types

Per-form material type config: label override, order, required, visible, step, conditional logic.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `form_id` | bigint FK | → forms, cascade delete |
| `material_type_id` | bigint FK | → material_types, cascade delete |
| `label_override` | string | Nullable |
| `sort_order` | smallint | Default 0 |
| `required` | boolean | Default false |
| `visible` | boolean | Default true |
| `step` | smallint | Default 2 |
| `conditional_logic` | json | Nullable; `{ match, rules }` |
| `timestamps` | | |

**Unique:** (`form_id`, `material_type_id`)

---

## form_custom_fields

Per-form custom field config: label override, order, required, visible, step, conditional logic.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `form_id` | bigint FK | → forms, cascade delete |
| `custom_field_id` | bigint FK | → custom_fields, cascade delete |
| `label_override` | string | Nullable |
| `sort_order` | smallint | Default 0 |
| `required` | boolean | Default false |
| `visible` | boolean | Default true |
| `step` | smallint | Default 2 |
| `conditional_logic` | json | Nullable |
| `timestamps` | | |

**Unique:** (`form_id`, `custom_field_id`)

---

## form_custom_field_options

Per-form overrides for a custom field option (label, order, visible, conditional logic). When no row exists, the base `custom_field_options` values apply.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `form_id` | bigint FK | → forms, cascade delete |
| `custom_field_option_id` | bigint FK | → custom_field_options, cascade delete |
| `label_override` | string | Nullable |
| `sort_order` | smallint | Default 0 |
| `visible` | boolean | Default true |
| `conditional_logic` | json | Nullable |
| `timestamps` | | |

**Unique:** (`form_id`, `custom_field_option_id`)

**Usage:** ILL and SFP should resolve `Form::bySlug('ill')` or `Form::bySlug('sfp')` and use the form’s `formMaterialTypes` / `formCustomFields` (and option overrides where needed) for what to show and how. Until wired, ILL still uses `MaterialType::activeForIll()` and `CustomField::forKind('ill')`. See [CLAUDE.md](../CLAUDE.md#forms-presentation-layer) for implementation notes.
