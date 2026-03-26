# Settings Reference

[← Back to README](README.md)

All settings are managed via `Setting::get(key, default)` and `Setting::set(key, value)`. Values are cached for 1 hour per key (`setting:{key}`).

Editable at **Settings → General** and **Settings → Catalog** in the staff UI.

---

## Group: `rate_limiting`

Controls how many requests a patron can submit within a rolling time window.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `sfp_limit_count` | integer | `5` | Maximum requests per patron within the window |
| `sfp_limit_window_days` | integer | `30` | Rolling window length in days |

**Usage in code:**

```php
$days  = (int) Setting::get('sfp_limit_window_days', 30);
$limit = Setting::get('sfp_limit_count', 5);
$since = now()->subDays($days);
```

---

## Group: `ill`

Interlibrary Loan age threshold.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `ill_age_threshold_days` | integer | `730` | Items older than this many days (~2 years) trigger the optional ILL path **after** catalog / ISBNdb resolution (step 3), not while entering the date |
| `ill_warning_message` | html | *(HTML)* | **Legacy:** previously shown below the date field; patron UI no longer uses it. Kept in the database for compatibility; safe to ignore or repurpose |

---

## Group: `messaging`

HTML messages shown to patrons at key moments.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `duplicate_request_message` | html | *(HTML)* | Shown when the submitted title matches another patron's existing request |
| `duplicate_self_request_message` | html | *(HTML)* | Shown when the patron has already requested the same title |
| `submission_success_message` | html | *(HTML)* | Shown on Step 4 (confirmation) after a successful submission |
| `catalog_owned_message` | html | *(HTML)* | Shown when the patron confirms the item is already in the catalog (no request is created) |
| `auto_order_author_exclusion_message` | html | *(HTML)* | Shown when a patron submits a future (unreleased) title by an author on the auto-order exclusion list (no request is created) |

---

## Group: `ordering`

Controls auto-order exclusions for popular authors.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `auto_order_author_exclusions` | text | *(empty)* | One author per line. If the item has a confidently-detected future release date and the author matches this list, the patron is shown the auto-order message and no request is created |

Notes:
- The “future release date” check is only applied when the date is unambiguous (e.g. `YYYY-MM-DD`, or a clearly future `YYYY-MM` / `YYYY`).

---

## Group: `catalog`

BiblioCommons catalog integration settings.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `catalog_search_enabled` | boolean | `1` | Enable/disable catalog search during submission |
| `catalog_library_slug` | string | `dcpl` | BiblioCommons subdomain (e.g. `dcpl` → `dcpl.bibliocommons.com`) |
| `catalog_search_url_template` | string | *(template)* | URL template for catalog search links. Placeholders: `{slug}`, `{query}` |

---

## Group: `syndetics`

Syndetics book cover image service.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `syndetics_client` | string | `davia` | Syndetics client ID. Leave empty to disable Syndetics and use fallback images only |

**URL format:**
```
https://www.syndetics.com/index.aspx?isbn={isbn}&issn=/LC.JPG&client={client}
```

---

## Group: `isbndb`

ISBNdb fallback search settings.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `isbndb_search_enabled` | boolean | `1` | Enable/disable ISBNdb fallback search |

The ISBNdb API key is stored in config (`ISBNDB_API_KEY` env), not in the settings table.

---

## Group: `processing`

Post-submission processing mode.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `post_submit_mode` | string | `wait` | `wait` = patron waits synchronously; `email` = send results by email when done |

---

## Type Reference

| Type | Input in admin UI | Notes |
|------|--------------------|-------|
| `string` | `<input type="text">` | |
| `integer` | `<input type="number">` | Keys ending in `_days` show "days" label |
| `boolean` | Checkbox | Stored as `'1'`/`'0'`; cast to `bool` on read |
| `text` | `<textarea>` | |
| `html` | Trix editor | Stored as HTML; cast to string on read |

---

## Adding a New Setting

1. Add a row to `SettingsSeeder::defaultSettings()` (the shared defaults array):

```php
[
    'key'         => 'my_new_setting',
    'value'       => 'default_value',
    'label'       => 'My New Setting',
    'type'        => 'string',
    'group'       => 'my_group',
    'description' => 'What this setting does.',
],
```

2. **SettingsSeeder** (full seed/overwrite):  
   `php artisan db:seed --class=Dcplibrary\\Sfp\\Database\\Seeders\\SettingsSeeder`  
   Use when resetting or initially seeding; it overwrites existing values.

3. **DefaultSettingsSeeder** (missing keys only):  
   `php artisan db:seed --class=Dcplibrary\\Sfp\\Database\\Seeders\\DefaultSettingsSeeder`  
   Use to ensure defaults exist without changing values already set in the database. Safe to run by class name anytime.

4. Read it in code: `Setting::get('my_new_setting', 'default')`
