# Code Review: Efficiency, Componentization, Hardcoding, SFP vs ILL

Recommendations from a review of the dcplibrary/requests Laravel package.

---

## 1. Efficiency and best use of code

### What’s working well
- **Eager loading:** `RequestController::index()` uses `PatronRequest::with(['patron', 'material', 'status', 'assignedTo', 'fieldValues.field'])`, which avoids N+1s on the list view.
- **Shared traits:** Both `RequestForm` and `IllForm` use the same concerns (`RemembersPatron`, `EvaluatesFieldConditions`, `FiltersFormFieldOptions`, `CreatesEnrichedMaterial`, `WithCoverService`), so material/patron/field logic is not duplicated.
- **Scopes:** `Field::scopeForKind()` and `RequestStatus::scopeForKind()` give a single, consistent way to filter by request kind.
- **Form-driven config:** Fields, statuses, and form config live in the DB and pivots (`forms`, `form_field_config`), so behavior is data-driven rather than hardcoded in PHP.

### Improvements to consider
- **RequestController::index() filter block:** Repeated pattern for `material_type` and `audience` (and custom filters): resolve field by key, then `whereExists` on `request_field_values`. Consider a small helper, e.g. `applyFieldValueFilter($query, $fieldKey, $value)` or a scope on `PatronRequest` like `whereFieldValue('material_type', $slug)`, to shorten the controller and centralize the subquery.
- **Field ID lookups in loops:** In index, `Field::where('key', 'material_type')->value('id')` and similar run per request; if you add more filterable fields, consider resolving all filterable field IDs once (e.g. from config or a cached list) and reusing.
- **RequestController size:** The controller is large (1,100+ lines). Extracting **authorization and validation** into Form Requests (e.g. `BulkDeleteRequest`, `BulkReassignRequest`) and **bulk operations** into dedicated action classes (e.g. `BulkDeleteRequests`, `BulkReassignRequests`) would improve readability and testability without changing behavior.

---

## 2. Componentization suggestions

### Blade components (already in good shape)
- Reusable pieces already live under `resources/views/components/` (e.g. `kind-badge`, `limit-reached`, `material-details`, `status-pill`, `patron-info`). Keep using these and add new ones when the same snippet appears in multiple views.

### Suggested extra componentization
1. **Patron step partial (Step 1)**  
   Both `request-form.blade.php` and `ill-form.blade.php` contain a full “patron” step (barcode, name, phone, email, notify_by_email for SFP). Extract a single Blade component, e.g. `<x-requests::patron-step :show-notify-by-email="true" />` (or pass a `formKind` and let the component decide), and use it in both forms to avoid drift and duplication.

2. **Request form “step shell”**  
   Shared layout for “step title + back/next + content” could be one component used by both forms so step navigation and layout stay consistent.

3. **Staff request filters bar**  
   The requests index filter row (kind, status, material type, audience, search, custom fields, assignment) is a long block in one view. Consider a component such as `<x-requests::staff.request-filters :filterable-fields="..." :statuses="..." />` that receives the same data the controller already prepares. That keeps the index view focused on layout and the filter bar reusable/testable.

4. **Admin form labels (SFP vs ILL)**  
   In `FormFieldController`, `CustomFieldController`, and several Blade files you have `$form === 'ill' ? 'Interlibrary Loan' : 'Suggest for Purchase'` or equivalent. Prefer resolving the label from the `Form` model (e.g. `Form::bySlug($form)?->name`) in the controller and passing it to the view, or a single Blade component that takes `formSlug` and looks up the name. That way labels stay in one place (DB or config).

5. **Bulk action bar**  
   The block that shows “Reassign”, “Bulk status”, “Delete”, etc. and the checkboxes could be a small component that receives “available actions” and “selected IDs” so the index view stays simple and the bar can be reused (e.g. on another list) or tested in isolation.

### Livewire
- **RequestForm vs IllForm:** Two components are appropriate (different flows and copy). To reduce duplication further, you could introduce an **abstract base** (e.g. `BaseRequestForm` or a trait) that holds:
  - Common step/patron state and `RemembersPatron` usage
  - Shared “get form by kind”, “get pending status for kind”, “get visible fields for kind”
  - Common submission wiring (create patron, create request, notify, redirect) parameterized by kind  
  Then `RequestForm` and `IllForm` extend or use that and only add SFP- or ILL-specific steps and UI. This is a larger refactor; the current trait-based sharing is already reasonable.

---

## 3. Hardcoded information that should be dynamic

### Kind slugs and labels
- **Current:** The strings `'sfp'` and `'ill'` appear in many places (controllers, models, Livewire, Blade, seeders). Display labels like “Suggest for Purchase” and “Interlibrary Loan” are repeated in Blade and PHP.
- **Recommendation:**
  - **Constants:** Add to `PatronRequest` (or a small `RequestKind` enum/class) e.g. `KIND_SFP = 'sfp'`, `KIND_ILL = 'ill'`, and use these wherever you branch on kind in code. That avoids typos and makes it easy to search for usages.
  - **Labels:** Use the `Form` model as the source of truth for display names: `Form::bySlug('sfp')->name` / `Form::bySlug('ill')->name`. Seed these once; then controllers and Blade should receive `$formName` or use a helper like `form_name('sfp')` instead of hardcoding “Suggest for Purchase” / “Interlibrary Loan” in multiple files.

### Config
- **Current:** `config/requests.php` covers route prefix, middleware, guard, ISBNdb, queue. Kind slugs and form-specific defaults are not in config.
- **Optional:** If you ever need to support more request types or rename “sfp”/“ill” in URLs, you could add something like `config('requests.kinds')` (e.g. `['sfp' => ['slug' => 'sfp', 'form_slug' => 'sfp'], 'ill' => [...]]`) and drive routes and scopes from that. Not required for the current two-kind design; the main win is **constants + Form names** for labels and slugs.

### Messages and copy
- **Current:** Some user-facing strings are in seeders (e.g. `ill_warning_message`, `barcode_not_found_message`) or in Blade. That’s good.
- **Watch for:** Any remaining inline “Interlibrary Loan” / “Suggest for Purchase” or “SFP” / “ILL” in views or flash messages should be replaced by the form name or a setting so a single change updates everywhere.

### URLs
- **Current:** `sfp_isbn_lookup_url`, `ill_isbn_lookup_url`, `polaris_leap_url` are already in settings. Good.
- **Polaris:** `PolarisService` / jobs use `.env` directly (`PAPI_*`). Consider resolving these via config (e.g. `config('requests.polaris.domain')`) so the package has one place for env mapping and the host app can override in config.

---

## 4. SFP vs ILL: separate vs shared

### Conclusion: **Similar enough to share most code; different enough to keep two entry points.**

- **Shared and working well:**  
  Same models (`PatronRequest`, `Patron`, `Material`, `Field`, `RequestStatus`), same staff controller and index (filter by `kind`), same scopes (`forKind`), same traits on both forms, same layout and many Blade components. One request show flow, one status dropdown driven by `request_kind`, one convert-to-ILL flow. Form and custom-field admin are already shared and kind-aware.

- **Intentionally separate:**  
  Two routes (`/sfp`, `/ill`), two Livewire components (`RequestForm`, `IllForm`), and two Blade views. That matches the product: two distinct flows (purchase suggestion vs borrow-from-elsewhere), different copy, different limits and settings (e.g. `ill_limit_count`, `ill_isbndb_enabled`, SFP duplicate/ILL warning). ILL access is also gated by `ill_selector_group_id` and `hasIllAccess()`, which justifies a separate tab and entry.

- **Where to share more:**
  - **Constants and labels:** Use `PatronRequest::KIND_*` (or similar) and `Form::bySlug(...)->name` everywhere so SFP/ILL are defined in one place.
  - **Patron step (Step 1):** Same fields and validation; one Blade partial or Livewire “patron step” used by both forms.
  - **Optional base form component/trait:** Shared “resolve form, fields, pending status by kind” and common submission steps, with SFP/ILL-specific steps and copy in the two existing components.

- **Where to keep separate:**
  - **Public routes and top-level components:** Keep `RequestForm` and `IllForm` as the two entry points so URLs and UX stay clear (SFP vs ILL).
  - **Step 2 and beyond:** SFP has catalog search, duplicate handling, ILL opt-in, genre/audience; ILL has different copy and limits. These can stay in their respective components; sharing is already done via traits and models where it matters.

---

## 5. Priority summary

| Priority | Action |
|----------|--------|
| **High** | Introduce `PatronRequest::KIND_SFP` / `KIND_ILL` (or enum) and use everywhere you branch on kind in PHP. |
| **High** | Resolve form display names from `Form::bySlug($form)->name` (or a helper) in controllers and Blade; remove repeated “Suggest for Purchase” / “Interlibrary Loan” strings. |
| **Medium** | Extract patron step (Step 1) into one Blade component used by both `request-form` and `ill-form`. |
| **Medium** | Add a small helper or scope for “filter by field value” in the request index to avoid repeating the `whereExists` + `request_field_values` pattern. |
| **Low** | Consider splitting `RequestController` (e.g. Form Requests for validation, action classes for bulk ops) and/or an abstract base for the two Livewire forms if you add more request types later. |
| **Low** | Centralize Polaris env in config so the package has a single place for env keys. |

Overall, the package is in good shape: shared models and scopes, trait-based form logic, and form-driven config. The biggest wins are centralizing kind slugs and labels and extracting the shared patron step; the rest can be done incrementally.
