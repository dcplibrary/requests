# dcplibrary/sfp — AI Assistant Rules

## Package Overview
Laravel package (`dcplibrary/sfp`) for DCP Library staff portal. Installed via Composer in host Laravel apps.

---

## CSS Build System

### How it works
The package ships its own compiled CSS — no host app configuration required.

- **Build command:** `npm run build:css` (run from the package root)
- **Input:** `resources/css/sfp.css` (Tailwind entry point)
- **Output:** `resources/dist/sfp.css` — **committed to the repo**, ships inside the package
- **Served via:** a dedicated asset route registered in `SfpServiceProvider`

### Asset route (Horizon/Telescope pattern)
`SfpServiceProvider::registerRoutes()` registers:
```
GET /{prefix}/assets/css  →  named route: request.assets.css
```
This streams `resources/dist/sfp.css` directly from inside `vendor/dcplibrary/sfp/` with a 1-year cache header. No files are copied to the host app's `public/` directory.

### Layout files
All four layout files load CSS via the named route:
```blade
<link rel="stylesheet" href="{{ route('request.assets.css') }}">
```
**Never** use `asset('vendor/sfp/css/sfp.css')` — that requires vendor:publish and is the old pattern.

### Deployment (prod server)
After `composer update dcplibrary/sfp`: **nothing extra needed.**
- `resources/dist/sfp.css` is already in the package
- The route serves it automatically
- No `vendor:publish --tag=sfp-assets`, no deploy script changes, no host app Tailwind config changes

### When to rebuild CSS
Run `npm run build:css` and commit `resources/dist/sfp.css` any time you:
- Add or change Tailwind classes in any view (`resources/views/**/*.blade.php`)
- Add or change Tailwind classes in any PHP source file (`src/**/*.php`)
- Change `resources/css/sfp.css` (the Tailwind entry point)

### Watch mode (local dev)
```bash
npm run watch:css
```
Watches for view/source changes and rebuilds `resources/dist/sfp.css` automatically. Still need to commit the rebuilt file before pushing.

---

## Publishable Tags

| Tag | What it publishes |
|---|---|
| `sfp-config` | `config/sfp.php` |
| `sfp-migrations` | database migrations |
| `sfp-seeders` | database seeders |
| `sfp-views` | Blade views (optional override only) |
| `sfp` | all of the above |

**`sfp-assets` no longer exists.** CSS is served via route, not published.

---

## Database Backup / Restore Notes

- SQLite `PDO::exec()` only handles one statement at a time — always use `splitSqlStatements()` in `BackupController` when executing restored SQL.
- `PRAGMA foreign_keys = OFF` and `PRAGMA foreign_keys = ON` must have trailing semicolons in exported SQL to be treated as separate statements by the splitter.
- `catalog_format_labels` schema: `id`, `format_code` (unique), `label`, timestamps. No `sort_order` column. Export with `->get(['format_code', 'label'])`, restore with `updateOrCreate(['format_code' => ...], ['label' => ...])`.
- Backup files generated before the semicolon fix are malformed — regenerate them.

---

## Polaris PAPI Integration (`blashbrook/papiclient`)

### Two-step authentication pattern
Polaris PAPI uses **two different auth contexts** depending on the endpoint:

- **Protected endpoints** (e.g. `/authenticator/staff`) — use `->protected()`, signed with the PAPI access key/secret via HMAC. No patron token needed.
- **Public patron endpoints** (e.g. `/patron/{barcode}/basicdata`) — use `->patron($barcode)->auth($accessSecret)`, where `$accessSecret` is the token obtained from the staff auth call above.

Every patron lookup therefore requires **two sequential calls**: authenticate as staff first, then use the returned `AccessSecret` as the patron-level token.

### Always use `new PAPIClient()` — never reuse instances
`PAPIClient` is registered as a singleton in the container. **Do not inject it or use `app(PAPIClient::class)`** — doing so bleeds state (method, uri, params, accessSecret) from one call into the next. Always instantiate fresh:

```php
// CORRECT
$auth = (new PAPIClient())->method('POST')->protected()->uri('/authenticator/staff')->params([...])->execRequest();
$data = (new PAPIClient())->method('GET')->patron($barcode)->auth($accessSecret)->uri('/basicdata')->execRequest();

// WRONG — state from $auth bleeds into second call
$client = app(PAPIClient::class);
$auth = $client->method('POST')->protected()->uri('/authenticator/staff')->params([...])->execRequest();
$data = $client->method('GET')->patron($barcode)->auth($accessSecret)->uri('/basicdata')->execRequest();
```

### URI construction rules
- `->protected()` uses `protectedURI` (no trailing slash) — so `->uri(...)` needs a **leading slash**: `->uri('/authenticator/staff')`
- `->patron($barcode)` uses `publicURI` and builds `patron/{barcode}` automatically — so `->uri(...)` also needs a **leading slash**: `->uri('/basicdata')`

### `execRequest()` return value
Returns an array directly — not a Response object. Patron basicdata is wrapped one level deep:
```php
$basicData = $data['PatronBasicData'] ?? $data;
```

### Field name gotcha: phone in basicdata
In the `basicdata` endpoint, the phone field is `PhoneNumber` (not `PhoneVoice1` as it appears elsewhere in the Polaris API). Map it explicitly:
```php
'PhoneVoice1' => $basicData['PhoneNumber'] ?? $basicData['CellPhone'] ?? null,
```

### Barcode validation is enforced — but fails open on API errors
The barcode check is a real gate: if Polaris is reachable and confirms the barcode does not exist, the patron is blocked and shown the `barcode_not_found_message`. This is intentional — only valid library cardholders should be able to submit.

`barcodeExists()` returns three distinct values:
- `true` — Polaris confirmed barcode exists → allow through
- `false` — Polaris confirmed barcode NOT found → **block**, show error message
- `null` — Polaris unavailable, timed out, or not configured → **allow through** (fail-open)

The fail-open (`null`) case only covers infrastructure failures, not confirmed invalid barcodes. The logic in `SfpForm::nextStep()` is:
```php
if ($exists === false) {
 $this->barcodeNotFound = true;
 return; // blocked
}
// null falls through — Polaris down, don't punish the patron
```

Returning patrons (barcode already in local DB) bypass the Polaris call entirely — no need to re-validate a known patron.

### Required `.env` keys
```
PAPI_DOMAIN= # staff auth domain
PAPI_STAFF= # staff username
PAPI_PASSWORD= # staff password
```
If any of these are missing, Polaris integration is skipped silently.

---

## Artisan Commands

```bash
php artisan sfp:backup # run a backup
php artisan sfp:backup --prune # run a backup then prune old files
```

Prune cutoff is controlled by the `backup_retention_days` setting (default: 30).

