# Livewire Form (SfpForm)

[← Back to README](README.md)

**Class:** `Dcplibrary\Sfp\Livewire\SfpForm`
**View:** `resources/views/livewire/sfp-form.blade.php`
**Layout:** `sfp::layouts.sfp`
**Route:** `GET /{prefix}` → name `request.form` (default prefix: `request`)

---

## Steps

| Step | Description |
|------|-------------|
| 1 | Patron Information (barcode, name, phone, email) |
| 2 | Material Details (type, audience, title, author, year, ILL opt-in) |
| 3 | Resolution (catalog results / ISBNdb results / no results) |
| 4 | Confirmation |

A processing overlay (`$processing = true`) replaces all step content while searches run.

---

## State Properties

### Navigation
| Property | Type | Default |
|----------|------|---------|
| `$step` | `int` | `1` |
| `$processing` | `bool` | `false` |
| `$processingStep` | `string` | `''` |

### Step 1 — Patron
| Property | Type |
|----------|------|
| `$barcode` | `string` |
| `$name_first` | `string` |
| `$name_last` | `string` |
| `$phone` | `string` |
| `$email` | `string` |

### Step 2 — Material
| Property | Type |
|----------|------|
| `$material_type_id` | `int\|null` |
| `$other_material_text` | `string` |
| `$audience_id` | `int\|null` |
| `$title` | `string` |
| `$author` | `string` |
| `$publish_date` | `string` |
| `$where_heard` | `string` |
| `$ill_requested` | `bool` |
| `$showIllWarning` | `bool` |

## Dynamic Step 2 form fields (`sfp_form_fields`)

Step 2’s fields are **config-driven** via the `sfp_form_fields` table (`Dcplibrary\Sfp\Models\FormField`).

- **Admin controls**: order (`sort_order`), label, active/required, and conditional logic rules.
- **Conditional logic inputs**: rules reference **slugs**, not IDs:
  - `material_type` → `material_types.slug`
  - `audience` → `audiences.slug`
- **Visibility evaluation**:
  - `SfpForm::formState()` builds `{ material_type: <slug>, audience: <slug> }` from the selected IDs.
  - `FormField::isVisibleFor($state)` evaluates `match: all|any` + `rules[]` (`in|not_in`).
  - `SfpForm::visibleFields` is a map of `key => bool` computed from the ordered fields.
  - The blade (`resources/views/livewire/sfp-form.blade.php`) loops `orderedFields` in DB order and renders only when `visibleFields[$field->key]` is true.
- **Hidden field clearing**:
  - When `material_type_id` or `audience_id` changes, `clearHiddenFields()` clears any now-hidden Livewire properties so stale values don’t “stick” across selections.
- **Validation**:
  - `buildStepTwoRules()` builds rules from the field config.
  - A field is only required when it is currently visible (`FormField::isRequiredFor($state)`), so hidden required fields validate as nullable.
- **Caching**:
  - Field config is cached via `FormField::allOrdered()` and is busted by the staff Form Fields UI (`FormField::bustCache()` on save).

The staff UI for this lives under **Settings → Form Fields** (Livewire admin components `FormFields` / `FormFieldEdit`), and option slugs used in conditional logic are protected from editing by `OptionsManager` (locked when referenced by any field condition).

### Persistence notes (what is saved where)

Even though Step 2 is “config-driven” for display/validation, the final persistence into `requests` is intentionally explicit in `SfpForm::saveRequest()`:

- **`genre`**: stored on `requests.genre` (slug)
- **`console` vs “Other” text**:
  - If the selected material type has `has_other_text = true`, the free-text input is saved to `requests.other_material_text`
  - Otherwise, if the `console` field is visible, the selected console slug is saved to `requests.other_material_text`
- **Other fields**: `title`, `author`, `where_heard`, `ill_requested`, etc. map directly to request columns (`submitted_*` for patron-entered bibliographic fields)

### Slug locking (why some slugs can’t be edited)

Option slugs for `material_types` and `audiences` are referenced by form-field conditional rules, so changing them can silently break visibility logic.

The admin Options UI prevents this in two layers:

- **UI**: shows the slug read-only when it’s referenced by any `FormField.condition` rule
- **Server-side guard**: `OptionsManager::updateItem()` refuses to change a slug that is referenced (it keeps the original slug even if a different one is submitted)

### Tests that lock in the decision tree

- `tests/Unit/SfpFormStepTwoRulesTest.php` verifies the key invariant: a field marked required in admin is **only required when currently visible** (hidden required fields validate as nullable).

### Step 3 — Resolution
| Property | Type | Description |
|----------|------|-------------|
| `$catalogResults` | `array` | Max 5 BiblioCommons results, each with `cover_url` |
| `$catalogSearched` | `bool` | |
| `$catalogMatchAccepted` | `bool\|null` | |
| `$catalogMatchBibId` | `string\|null` | |
| `$isbndbResults` | `array` | Max 5 ISBNdb results, each with `cover_url` |
| `$isbndbSearched` | `bool` | |
| `$isbndbMatchAccepted` | `bool\|null` | |
| `$selectedIsbndbIndex` | `int\|null` | |
| `$isDuplicate` | `bool` | |
| `$duplicateMessage` | `string` | |
| `$resolvedMaterialId` | `int\|null` | |

---

## Methods

### Lifecycle

| Method | Description |
|--------|-------------|
| `mount()` | Pre-selects first active material type and "Adult" audience |
| `render()` | Returns view with `materialTypes`, `audiences`, `illWarningMessage`, `successMessage`, `duplicateMessage`, `formatLabels` |

### Navigation

| Method | Description |
|--------|-------------|
| `nextStep()` | Validates step 1 fields; checks patron rate limit; advances to step 2 |
| `prevStep()` | Returns to previous step |
| `updatedPublishDate($value)` | Triggers ILL age warning check |

### Main Submission Flow (`submit()`)

Called from the Step 2 "Submit Request" button. Runs synchronously:

```
1. Validate full form (steps 1 + 2 fields)
2. PatronService::findOrCreate() → Patron + queues Polaris job if new
3. Check patron rate limit → error if exceeded
4. Material::findMatch(title, author) → check for duplicate request
5. If no existing material:
   a. BibliocommonsService::search() → if results → step 3 (catalog)
   b. Else IsbnDbService::search() → if results → step 3 (ISBNdb)
   c. Else → saveRequest() directly → step 4
6. If existing material found → saveRequest(patron, null) → step 4
   (duplicate flag set if another request already uses this material)
```

### Step 3 Interactions

| Method | Description |
|--------|-------------|
| `acceptCatalogMatch(string $bibId)` | Records accepted bib ID and ends the flow **without creating a request** (item is already owned) |
| `skipCatalogMatch()` | Skips catalog; shows ISBNdb results (if any) or saves directly |
| `acceptIsbndbMatch(int $index)` | Records selected ISBNdb result index; calls `finishAfterResolution()` |
| `skipIsbndbMatch()` | Saves request without ISBNdb data |

### Private Helpers

| Method | Description |
|--------|-------------|
| `checkPatronLimit()` | Returns error string if patron has hit rate limit, null otherwise |
| `finishAfterResolution()` | Called after patron accepts or skips a result set; proceeds to `saveRequest()` |
| `saveRequest(Patron, ?array $isbndbData)` | Creates `Material` (or reuses match), creates `SfpRequest`, logs initial status history, advances to step 4 |
| `withCovers(array $results, string $source)` | Decorates each result with `cover_url` via `CoverService` |

---

## Duplicate Detection

On `submit()`:

1. `Material::findMatch($title, $author)` — case-insensitive search
2. If found, check if any `SfpRequest` already references that material
3. If yes and it's the **same patron**: set `$duplicateMessage` from `duplicate_self_request_message` setting; request is still saved with `is_duplicate = true`
4. If yes and it's a **different patron**: set `$duplicateMessage` from `duplicate_request_message` setting; request saved with `is_duplicate = true, duplicate_of_request_id = {existing id}`

---

## ILL Warning

When `$publish_date` is updated, `updatedPublishDate()` checks:
- Is the value a 4-digit year?
- Does `Material::yearExceedsIllThreshold($year)` return true?

If yes, `$showIllWarning = true` and the configured `ill_warning_message` HTML is shown below the date field.

---

## Cover Images

`withCovers()` is called on every result set before storing in component state:

```php
// Catalog results
$isbn     = $result['isbns'][0] ?? null;
$fallback = $result['jacket'] ?? null;

// ISBNdb results
$isbn     = $result['isbn13'] ?? $result['isbn'] ?? null;
$fallback = $result['image'] ?? null;

$result['cover_url'] = CoverService::url($isbn, $fallback);
```

The blade renders `$result['cover_url']` directly — no cover logic in the view.

---

## Format Labels

`$formatLabels` is passed to the view from `CatalogFormatLabel::map()`. In the catalog results display:

```blade
{{ $formatLabels[$result['format']] ?? $result['format'] }}
```

Falls back to the raw code (`BK`, `LPRINT`, etc.) if no label is defined.
