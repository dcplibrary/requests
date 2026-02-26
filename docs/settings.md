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

Interlibrary Loan age threshold and warning message.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `ill_age_threshold_days` | integer | `730` | Items older than this many days trigger the ILL soft warning (~2 years) |
| `ill_warning_message` | html | *(HTML)* | Message shown when item exceeds threshold; displayed on Step 2 below the date field |

---

## Group: `messaging`

HTML messages shown to patrons at key moments.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `duplicate_request_message` | html | *(HTML)* | Shown when the submitted title matches another patron's existing request |
| `duplicate_self_request_message` | html | *(HTML)* | Shown when the patron has already requested the same title |
| `submission_success_message` | html | *(HTML)* | Shown on Step 4 (confirmation) after a successful submission |

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

The ISBNdb API key is stored in config (`SFP_ISBNDB_KEY` env), not in the settings table.

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

1. Add a row to `SettingsSeeder::run()`:

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

2. Re-run the seeder: `php artisan db:seed --class=Dcplibrary\\Sfp\\Database\\Seeders\\SettingsSeeder`

3. Read it in code: `Setting::get('my_new_setting', 'default')`
