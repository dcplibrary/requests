# Background Jobs

[← Back to README](README.md)

Jobs live in `src/Jobs/`.

---

## LookupPatronInPolaris

**Class:** `Dcplibrary\Sfp\Jobs\LookupPatronInPolaris`

Validates a patron against the Polaris ILS and enriches the patron record with authoritative contact data.

### Dispatch

Dispatched automatically by `PatronService::findOrCreate()` when a **new** patron is created:

```php
LookupPatronInPolaris::dispatch($patron->id);
```

Can also be re-dispatched manually by staff via the **Retrigger Polaris** button on the patron detail page (`PatronController::retriggerPolaris()`), which first resets `polaris_lookup_attempted = false`.

### Queue Settings

| Property | Default |
|----------|---------|
| `tries` | 3 |
| `backoff` | 30 seconds |
| Connection | `config('sfp.queue.connection')` → `SFP_QUEUE_CONNECTION` env |
| Queue name | `config('sfp.queue.name')` → `SFP_QUEUE_NAME` env |

### What It Does

1. Authenticates with the Polaris PAPI as a staff user (via `blashbrook/papiclient`)
2. Fetches `PatronBasicData` by barcode
3. Calls `$patron->applyPolarisData($data)` on success
4. Calls `$patron->markPolarisNotFound()` if no record found
5. On any exception: logs the error and returns silently — patron submission is not blocked

### Match Fields

After `applyPolarisData()` runs, the patron record has:

| Field | True if |
|-------|---------|
| `name_first_matches` | Normalized first name matches Polaris |
| `name_last_matches` | Normalized last name matches Polaris |
| `phone_matches` | Digits-only phone matches Polaris |
| `email_matches` | Lowercased email matches Polaris |

Mismatches surface the patron in the staff **Patrons** view flagged list for review.

---

## ProcessSfpRequest

**Class:** `Dcplibrary\Sfp\Jobs\ProcessSfpRequest`

> **Note:** This job is a placeholder/stub. The actual catalog and ISBNdb search processing happens synchronously within the `SfpForm` Livewire component during form submission. This job exists for a potential future async processing mode (controlled by the `post_submit_mode` setting).

| Property | Value |
|----------|-------|
| `tries` | 2 |
