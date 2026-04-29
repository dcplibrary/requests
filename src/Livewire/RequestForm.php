<?php

namespace Dcplibrary\Requests\Livewire;

use Dcplibrary\Requests\Livewire\Concerns\CreatesEnrichedMaterial;
use Dcplibrary\Requests\Livewire\Concerns\EvaluatesFieldConditions;
use Dcplibrary\Requests\Livewire\Concerns\FiltersFormFieldOptions;
use Dcplibrary\Requests\Livewire\Concerns\RemembersPatron;
use Dcplibrary\Requests\Livewire\Concerns\WithCoverService;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\CatalogFormatLabel;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\Material;
use Dcplibrary\Requests\Models\Patron;
use Dcplibrary\Requests\Models\RequestFieldValue;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Services\BibliocommonsService;
use Dcplibrary\Requests\Services\IsbnDbService;
use Dcplibrary\Requests\Services\PatronService;
use Dcplibrary\Requests\Services\NotificationService;
use Dcplibrary\Requests\Services\PolarisService;
use Dcplibrary\Requests\Services\TurnstileService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Multi-step Suggest for Purchase patron submission form (catalog, ISBNdb, material, submit).
 */
#[Layout('requests::layouts.requests')]
class RequestForm extends Component
{
    use CreatesEnrichedMaterial;
    use EvaluatesFieldConditions;
    use FiltersFormFieldOptions;
    use RemembersPatron;
    use WithCoverService;

    // --- Steps ---
    public int $step = 1; // 1=patron, 2=material, 3=resolution, 4=confirmation

    // --- Step 1: Patron ---
    #[Validate('required|min:5|max:20')]
    public string $barcode = '';

    #[Validate('required|min:1|max:100')]
    public string $name_first = '';

    #[Validate('required|min:1|max:100')]
    public string $name_last = '';

    #[Validate('required|min:7|max:20')]
    public string $phone = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    public bool $notify_by_email = false;

    // --- Step 2: Material ---
    // Note: validation rules for these fields are built dynamically in buildStepTwoRules()
    // based on each field's 'active', 'required', and 'condition' config in sfp_form_fields.
    public ?int $material_type_id = null;

    public string $other_material_text = '';

    public string $genre = '';

    public string $console = '';

    public ?int $audience_id = null;

    public string $title = '';

    public string $author = '';

    public string $isbn = '';

    public string $publish_date = '';

    /** @var array<string, mixed> SFP custom field values (e.g. where_heard, console, ill_requested) */
    public array $custom = [];

    // --- Patron limit ---
    public bool    $limitReached = false;
    public ?string $limitUntil   = null; // formatted date string, e.g. "June 15, 2025"

    // --- Barcode not found in Polaris ---
    public bool $barcodeNotFound = false;
    public string $barcodeNotFoundMessage = '';

    // --- Step 3: Resolution state ---
    public ?int $resolvedMaterialId = null; // set if local material match found
    public bool $isDuplicate = false;
    public string $duplicateMessage = '';

    // Catalog results passed to view
    public array $catalogResults = [];
    public bool $catalogSearched = false;
    public ?string $catalogMatchBibId = null;
    public ?string $catalogFoundUrl = null;

    // ISBNdb results
    public array $isbndbResults = [];
    public bool $isbndbSearched = false;

    // Whether patron accepted or skipped a match
    public ?bool $catalogMatchAccepted = null;
    public ?bool $isbndbMatchAccepted = null;
    public ?int $selectedIsbndbIndex = null;

    // Processing state
    public bool $processing = false;
    public string $processingStep = '';

    // Final request ID
    public ?int $createdRequestId = null;

    // Auto-order exclusion handling (popular authors)
    public bool $autoOrderExcluded = false;
    public string $autoOrderExcludedMessage = '';

    // ILL suggestion (item exceeds age threshold — offer to submit as ILL instead)
    public bool $suggestIll = false;
    public ?int $pendingIsbndbIndex = null;

    public function mount(): void
    {
        $this->hydratePatronFromSession();

        // Pre-select "Book" as default material type
        $mtField = Field::where('key', 'material_type')->first();
        if ($mtField) {
            $book = FieldOption::where('field_id', $mtField->id)->where('slug', 'book')->where('active', true)->first();
            if ($book) {
                $this->material_type_id = $book->id;
            }
        }

        // Pre-select "Adult" as default audience
        $audField = Field::where('key', 'audience')->first();
        if ($audField) {
            $adult = FieldOption::where('field_id', $audField->id)->where('slug', 'adult')->where('active', true)->first();
            if ($adult) {
                $this->audience_id = $adult->id;
            }
        }
    }

    // --- Step navigation ---

    public function nextStep(): void
    {
        if ($this->step === 1) {
            // Reset barcode-not-found state on each attempt
            $this->barcodeNotFound = false;
            $this->barcodeNotFoundMessage = '';

            // Verify Turnstile CAPTCHA before doing anything else.
            if (Setting::get('captcha_enabled', false)) {
                if (! app(TurnstileService::class)->verify($this->turnstileToken, request()->ip())) {
                    $this->addError('turnstileToken', 'CAPTCHA verification failed. Please try again.');
                    $this->dispatch('turnstile-reset');
                    $this->turnstileToken = '';
                    return;
                }
            }

            $this->validate($this->patronValidationRules());

            $this->checkPatronLimit();

            // Only check Polaris if the feature is enabled and the barcode is new.
            // Returning patrons (already in the local DB) bypass the API call.
            if (
                Setting::get('polaris_barcode_check_enabled', true)
                && ! Patron::where('barcode', $this->barcode)->exists()
            ) {
                $exists = app(PolarisService::class)->barcodeExists($this->barcode);

                if ($exists === false) {
                    // Barcode explicitly not found in Polaris — stop here.
                    $this->barcodeNotFound = true;
                    $this->barcodeNotFoundMessage = (string) Setting::get(
                        'barcode_not_found_message',
                        '<p>The card number you entered was not found. Please apply for a library card online or visit the library to register.</p>'
                    );
                    return;
                }
                // $exists === null means Polaris is unavailable or not configured — let through.
            }
        }

        if ($this->step === 2) {
            $this->validate($this->buildStepTwoRules());
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /**
     * Keep patron info and start a new request on Step 2.
     */
    public function submitAnotherRequest(): void
    {
        $this->savePatronToSession();

        // Reset item + resolution state only (keep patron fields)
        $this->reset([
            'title',
            'author',
            'publish_date',
            'console',
            'custom',
            'other_material_text',
            'genre',
            'resolvedMaterialId',
            'isDuplicate',
            'duplicateMessage',
            'catalogResults',
            'catalogSearched',
            'catalogMatchBibId',
            'catalogFoundUrl',
            'isbndbResults',
            'isbndbSearched',
            'catalogMatchAccepted',
            'isbndbMatchAccepted',
            'selectedIsbndbIndex',
            'processing',
            'processingStep',
            'createdRequestId',
            'suggestIll',
            'pendingIsbndbIndex',
        ]);

        $this->resetValidation();
        $this->step = 2;
    }

    // --- Clear hidden fields when material type or audience changes ---

    public function updatedMaterialTypeId(): void
    {
        $this->clearHiddenFields();
    }

    public function updatedAudienceId(): void
    {
        $this->clearHiddenFields();
    }

    private function clearHiddenFields(): void
    {
        foreach ($this->formFields as $field) {
            // Never clear the controlling selectors here.
            if (in_array($field->key, ['material_type', 'audience'], true)) {
                continue;
            }

            if (! $this->fieldVisible($field->key)) {
                $this->clearFieldValue($field->key);
            }
        }

        foreach ($this->stepTwoCustomFields as $field) {
            if (! $this->customFieldVisible($field->key)) {
                unset($this->custom[$field->key]);
            }
        }

        // Clear "other" text when it isn't currently shown.
        if (! $this->showOtherText) {
            $this->other_material_text = '';
        }
    }

    private function clearFieldValue(string $key): void
    {
        if ($this->stepTwoCustomFields->contains('key', $key)) {
            unset($this->custom[$key]);
            return;
        }

        // Generic: clear any matching public string property
        if (property_exists($this, $key) && is_string($this->{$key})) {
            $this->{$key} = '';
        }
    }

    // --- Material type "Other" toggle (inline text within material type radio row) ---

    public function getShowOtherTextProperty(): bool
    {
        if (! $this->material_type_id) {
            return false;
        }
        $option = FieldOption::find($this->material_type_id);
        return $option?->meta('has_other_text', false) ?? false;
    }

    // --- Form field config (cached) ---

    /** @var string[] Field keys rendered in the core form-field loop (dedicated or generic fallback blocks). */
    private const CORE_KEYS = ['material_type', 'audience', 'genre', 'title', 'author', 'isbn', 'publish_date', 'console'];

    /**
     * Return the core SFP form fields, ordered and filtered by per-form config.
     *
     * When a form_field_config row exists for the SFP form, sort_order, required,
     * label_override, and conditional_logic are overlaid onto the Field model so
     * that isVisibleFor() / isRequiredFor() respect per-form settings.
     *
     * @return \Illuminate\Support\Collection<int, \Dcplibrary\Requests\Models\Field>
     */
    public function getFormFieldsProperty()
    {
        $form = Form::bySlug(PatronRequest::KIND_SFP);

        if ($form) {
            $configs = $form->fieldConfigs()
                ->with('field')
                ->where('visible', true)
                ->orderBy('sort_order')
                ->get();

            $fields = collect();
            foreach ($configs as $cfg) {
                $f = $cfg->field;
                if (! $f || ! $f->active || ! in_array($f->key, self::CORE_KEYS, true)) {
                    continue;
                }
                $this->overlayConfig($f, $cfg);
                $fields->push($f);
            }

            if ($fields->isNotEmpty()) {
                return $this->ensureCoreSfpFieldsPresent($fields);
            }
        }

        // Fallback: no SFP form config — use base fields directly
        return Field::forKind(PatronRequest::KIND_SFP)
            ->whereIn('key', self::CORE_KEYS)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * When staff form config omits rows, step 2 can show "Material Details" with no
     * material type / audience controls. Always merge these active SFP fields in.
     *
     * @param  \Illuminate\Support\Collection<int, \Dcplibrary\Requests\Models\Field>  $fields
     * @return \Illuminate\Support\Collection<int, \Dcplibrary\Requests\Models\Field>
     */
    private function ensureCoreSfpFieldsPresent(\Illuminate\Support\Collection $fields): \Illuminate\Support\Collection
    {
        $requiredKeys = ['material_type', 'audience'];
        $present      = $fields->pluck('key')->all();
        $missing      = collect();

        foreach ($requiredKeys as $key) {
            if (in_array($key, $present, true)) {
                continue;
            }
            $field = Field::forKind(PatronRequest::KIND_SFP)
                ->where('key', $key)
                ->active()
                ->first();
            if ($field) {
                $missing->push($field);
            }
        }

        if ($missing->isEmpty()) {
            return $fields->values();
        }

        return $missing->concat($fields)->values();
    }

    /**
     * Return the set of field keys that are visible given the current form state.
     *
     * @return array<string, bool>  ['genre' => true, 'console' => false, ...]
     */
    public function getVisibleFieldsProperty(): array
    {
        return $this->buildVisibilityMap($this->formFields);
    }

    /**
     * True when the given field key is both active and passes its condition.
     *
     * @param  string  $key
     * @return bool
     */
    public function fieldVisible(string $key): bool
    {
        return $this->isFieldVisible($key, $this->visibleFields);
    }

    /**
     * Additional SFP fields for step 2 (e.g. where_heard, ill_requested).
     * These are fields NOT in the core formFields set, with per-form config applied.
     *
     * @return \Illuminate\Support\Collection<int, Field>
     */
    public function getStepTwoCustomFieldsProperty()
    {
        $form = Form::bySlug(PatronRequest::KIND_SFP);

        if ($form) {
            $configs = $form->fieldConfigs()
                ->with('field')
                ->where('visible', true)
                ->orderBy('sort_order')
                ->get();

            $fields = collect();
            foreach ($configs as $cfg) {
                $f = $cfg->field;
                if (! $f || ! $f->active || in_array($f->key, self::CORE_KEYS, true)) {
                    continue;
                }
                $this->overlayConfig($f, $cfg);
                $fields->push($f);
            }

            if ($fields->isNotEmpty()) {
                return $fields->values();
            }
        }

        // Fallback: no SFP form config — use base fields directly
        return Field::forKind(PatronRequest::KIND_SFP)
            ->where('step', 2)
            ->whereNotIn('key', self::CORE_KEYS)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Overlay per-form config (sort_order, required, label, condition) onto a Field.
     *
     * @param  Field                                              $field
     * @param  \Dcplibrary\Requests\Models\FormFieldConfig  $cfg
     * @return void
     */
    private function overlayConfig(Field $field, \Dcplibrary\Requests\Models\FormFieldConfig $cfg): void
    {
        $field->sort_order = $cfg->sort_order;
        $field->required   = (bool) $cfg->required;

        if ($cfg->label_override !== null && $cfg->label_override !== '') {
            $field->label = $cfg->label_override;
        }
        if ($cfg->conditional_logic) {
            $field->condition = $cfg->conditional_logic;
        }
    }

    /**
     * @return array<string, bool>
     */
    public function getVisibleCustomFieldsProperty(): array
    {
        return $this->buildVisibilityMap($this->stepTwoCustomFields);
    }

    /**
     * True when the given custom field key passes its condition.
     *
     * @param  string  $key
     * @return bool
     */
    public function customFieldVisible(string $key): bool
    {
        return $this->isFieldVisible($key, $this->visibleCustomFields);
    }

    /**
     * Build the step-2 validation rules based on field config.
     * Required fields that are currently hidden are treated as nullable.
     *
     * @return array<string, string>
     */
    private function buildStepTwoRules(): array
    {
        // Fixed base rules that are always present regardless of field config
        $rules = [
            'material_type_id' => 'required|exists:field_options,id',
            'audience_id'      => 'required|exists:field_options,id',
        ];

        $sfpForm   = Form::bySlug(PatronRequest::KIND_SFP);
        $sfpFormId = $sfpForm?->id;
        $state     = $this->formConditionState();

        // Rules for core fields that have dedicated rendering blocks
        $fieldRuleMap = [
            'genre'        => function (bool $req) use ($sfpFormId) {
                $genreField = Field::where('key', 'genre')->first();
                return $genreField
                    ? $this->selectOrRadioRule($genreField, $req, $sfpFormId)
                    : ($req ? 'required' : 'nullable');
            },
            'title'        => fn (bool $req) => $req ? 'required|min:1|max:500' : 'nullable|min:1|max:500',
            'author'       => fn (bool $req) => $req ? 'required|min:1|max:300' : 'nullable|min:1|max:300',
            'isbn'         => fn (bool $req) => $req ? 'required|string|max:20' : 'nullable|string|max:20',
            'publish_date' => fn ()           => 'nullable|string|max:50',
        ];

        foreach ($this->formFields as $field) {
            // material_type and audience have fixed rules above
            if (in_array($field->key, ['material_type', 'audience'], true)) {
                continue;
            }

            $required = $field->isRequiredFor($state);

            if (isset($fieldRuleMap[$field->key])) {
                $rules[$field->key] = $fieldRuleMap[$field->key]($required);
                continue;
            }

            // Generic rules for unmapped core fields based on type
            $rules[$field->key] = match ($field->type) {
                'select', 'radio' => $this->selectOrRadioRule($field, $required, $sfpFormId),
                'textarea'        => $required ? 'required|string|max:5000' : 'nullable|string|max:5000',
                'text'            => $required ? 'required|string|max:500' : 'nullable|string|max:500',
                default           => $required ? 'required' : 'nullable',
            };
        }

        foreach ($this->stepTwoCustomFields as $field) {
            $visible  = $field->isVisibleFor($state);
            $required = $visible && $field->required;
            $path = "custom.{$field->key}";
            $rules[$path] = match ($field->type) {
                'textarea'        => $required ? 'required|string|max:5000' : 'nullable|string|max:5000',
                'text'            => $required ? 'required|string|max:500' : 'nullable|string|max:500',
                'select', 'radio' => $this->selectOrRadioRule($field, $required, $sfpFormId),
                'checkbox'        => 'nullable|boolean',
                default           => $required ? 'required' : 'nullable',
            };
        }

        return $rules;
    }

    // --- Main submission ---

    public function submit(): void
    {
        $this->validate($this->buildStepTwoRules());

        $this->processing = true;
        $this->processingStep = 'Saving your information...';

        // 1. Find or create patron
        $patronService = app(PatronService::class);
        ['patron' => $patron] = $patronService->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ]);

        // 2. Rate limit check
        if ($patron->hasReachedLimit()) {
            $this->limitReached = true;
            $this->limitUntil   = $patron->nextAvailableDate()?->format('F j, Y');
            $this->processing   = false;
            return;
        }

        // 2.5 Auto-order exclusion: only if we can confidently determine the
        // item is not yet released (future date).
        if ($this->shouldAutoOrderExclude($this->author, $this->publish_date)) {
            $this->autoOrderExcluded = true;
            $this->autoOrderExcludedMessage = (string) Setting::get(
                'auto_order_author_exclusion_message',
                '<p><strong>Good news:</strong> the library automatically orders new releases from this author. Please check the catalog closer to the release date to place a hold.</p>'
            );
            $this->processing = false;
            $this->step = 4;
            return;
        }

        // 3. Check local materials table for a match
        $this->processingStep = 'Checking our records...';
        $existingMaterial = Material::findMatch($this->title, $this->author);

        if ($existingMaterial) {
            // Check if any existing requests reference this material
            $priorRequest = PatronRequest::where('material_id', $existingMaterial->id)->first();
            if ($priorRequest) {
                $this->isDuplicate = true;

                // Use a different message if it's the same patron re-requesting
                if ($priorRequest->patron_id === $patron->id) {
                    $this->duplicateMessage = Setting::get(
                        'duplicate_self_request_message',
                        "You've already requested this item. We'll let you know when it's available."
                    );
                } else {
                    $this->duplicateMessage = Setting::get(
                        'duplicate_request_message',
                        'This item has already been requested. Please check the catalog regularly.'
                    );
                }
                $this->resolvedMaterialId = $existingMaterial->id;

                // Surface the duplicate notice before doing any further work.
                // Advance to step 3 so the patron sees the message. If they
                // choose to submit anyway, saveRequest() will still record the
                // request with is_duplicate=true.
                $this->processing = false;
                $this->step = 3;
                return;
            }
        }

        // 4. Catalog search
        if (! $this->isDuplicate && Setting::get('catalog_search_enabled', true)) {
            $this->processingStep = 'Searching our catalog...';
            $audienceOption = FieldOption::find($this->audience_id);
            $service = app(BibliocommonsService::class);
            $result = $service->search(
                $this->title,
                $this->author,
                $audienceOption?->meta('bibliocommons_value', 'adult') ?? 'adult',
                $this->publish_date ?: null
            );
            $this->catalogSearched = true;
            $this->catalogResults = $this->withCovers($result['results'], 'catalog');

            if (count($this->catalogResults) > 0) {
                // Show results to patron — pause here, patron interacts
                $this->processing = false;
                $this->step = 3;
                return;
            }
        }

        // 5. ISBNdb search (no catalog hits)
        $materialTypeOpt = $this->material_type_id ? FieldOption::find($this->material_type_id) : null;
        if (! $this->isDuplicate && Setting::get('isbndb_search_enabled', true) && $materialTypeOpt?->meta('isbndb_searchable', false)) {
            $this->processingStep = 'Searching book database...';
            $service = app(IsbnDbService::class);
            $result = $service->search($this->title, $this->author);
            $this->isbndbSearched = true;
            $this->isbndbResults = $this->withCovers($result['results'], 'isbndb');

            if (count($this->isbndbResults) > 0) {
                $this->processing = false;
                $this->step = 3;
                return;
            }
        }

        // 6. No catalog/ISBNdb match — offer ILL from patron-entered date, or save.
        if (! $this->offerIllPromptFromPatronEnteredDate()) {
            $this->saveRequest($patron);
        }
    }

    public function acceptCatalogMatch(string $bibId): void
    {
        $this->catalogMatchAccepted = true;
        $this->catalogMatchBibId = $bibId;

        // If the patron confirms the item is already in our catalog, we do NOT
        // create an SFP request. Direct them to place a hold instead.
        $match = collect($this->catalogResults)->firstWhere('bib_id', $bibId);
        $this->catalogFoundUrl = is_array($match) ? ($match['catalog_url'] ?? null) : null;

        $this->processing = false;
        $this->step = 4;
    }

    public function skipCatalogMatch(): void
    {
        $this->catalogMatchAccepted = false;

        // Move on to ISBNdb if not yet searched
        $materialTypeOpt = $this->material_type_id ? FieldOption::find($this->material_type_id) : null;
        if (! $this->isbndbSearched && Setting::get('isbndb_search_enabled', true) && $materialTypeOpt?->meta('isbndb_searchable', false)) {
            $service = app(IsbnDbService::class);
            $result = $service->search($this->title, $this->author);
            $this->isbndbSearched = true;
            $this->isbndbResults = $this->withCovers($result['results'], 'isbndb');

            if (count($this->isbndbResults) > 0) {
                return; // Stay on step 3, show ISBNdb results
            }
        }

        // No ISBNdb results — offer ILL from patron-entered date, or save.
        $patron = app(PatronService::class)->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ])['patron'];

        if (! $this->offerIllPromptFromPatronEnteredDate()) {
            $this->saveRequest($patron);
        }
    }

    public function acceptIsbndbMatch(int $index): void
    {
        $isbndbData = $this->isbndbResults[$index] ?? null;

        // If the selected item exceeds the ILL age threshold, prompt the patron
        // to consider submitting as an ILL instead — don't save the request yet.
        if (is_array($isbndbData) && Material::stringExceedsIllThreshold((string) ($isbndbData['publish_date'] ?? ''))) {
            $this->suggestIll         = true;
            $this->pendingIsbndbIndex = $index;
            return;
        }

        $this->isbndbMatchAccepted = true;
        $this->selectedIsbndbIndex = $index;

        // If ISBNdb can provide an unambiguous future publish date, honor the
        // auto-order exclusion list before creating a request.
        if (is_array($isbndbData) && $this->shouldAutoOrderExclude($this->author, (string) ($isbndbData['publish_date'] ?? ''))) {
            $this->autoOrderExcluded = true;
            $this->autoOrderExcludedMessage = (string) Setting::get(
                'auto_order_author_exclusion_message',
                '<p><strong>Good news:</strong> the library automatically orders new releases from this author. Please check the catalog closer to the release date to place a hold.</p>'
            );
            $this->processing = false;
            $this->step = 4;
            return;
        }

        $patron = app(PatronService::class)->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ])['patron'];

        $this->saveRequest($patron, $isbndbData);
    }

    public function skipIsbndbMatch(): void
    {
        $this->isbndbMatchAccepted = false;

        $patron = app(PatronService::class)->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ])['patron'];

        if ($this->offerIllPromptFromPatronEnteredDate()) {
            return;
        }

        $this->saveRequest($patron);
    }

    /**
     * Patron chose to continue as an SFP suggestion despite the ILL age threshold.
     * Resume the normal acceptIsbndbMatch flow using the stored pending index.
     */
    public function proceedAsSfp(): void
    {
        $this->suggestIll = false;
        $index = $this->pendingIsbndbIndex;
        $this->pendingIsbndbIndex = null;

        if ($index === null) {
            $patron = app(PatronService::class)->findOrCreate([
                'barcode'    => $this->barcode,
                'name_first' => $this->name_first,
                'name_last'  => $this->name_last,
                'phone'      => $this->phone,
                'email'      => $this->email ?: null,
            ])['patron'];
            $this->saveRequest($patron);

            return;
        }

        $this->isbndbMatchAccepted = true;
        $this->selectedIsbndbIndex = $index;

        $isbndbData = $this->isbndbResults[$index] ?? null;

        if (is_array($isbndbData) && $this->shouldAutoOrderExclude($this->author, (string) ($isbndbData['publish_date'] ?? ''))) {
            $this->autoOrderExcluded = true;
            $this->autoOrderExcludedMessage = (string) Setting::get(
                'auto_order_author_exclusion_message',
                '<p><strong>Good news:</strong> the library automatically orders new releases from this author. Please check the catalog closer to the release date to place a hold.</p>'
            );
            $this->processing = false;
            $this->step = 4;
            return;
        }

        $patron = app(PatronService::class)->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ])['patron'];

        $this->saveRequest($patron, $isbndbData);
    }

    /**
     * Save patron session data and material prefill, then redirect to the ILL form.
     * Used when the patron wants to submit as an ILL instead of SFP.
     */
    public function redirectToIll(): void
    {
        $this->savePatronToSession();

        session()->put('request.ill_prefill', [
            'title'            => $this->title,
            'author'           => $this->author,
            'publish_date'     => $this->publish_date,
            'material_type_id' => $this->material_type_id,
        ]);

        // Signal the ILL form to skip step 1 — patron data is already in session.
        session()->put('request.ill_skip_patron', true);

        $this->redirect(route('request.ill.form'));
    }

    /**
     * Show step 3 ILL vs SFP when the patron-entered publish date is past the threshold
     * and no ISBNdb row will be applied (no hits, skipped, or after skipping catalog with no ISBNdb).
     *
     * @return bool True if the prompt is shown — caller must not call saveRequest() yet.
     */
    private function offerIllPromptFromPatronEnteredDate(): bool
    {
        if (! Material::stringExceedsIllThreshold($this->publish_date)) {
            return false;
        }

        $this->suggestIll = true;
        $this->pendingIsbndbIndex = null;
        $this->processing = false;
        $this->step = 3;

        return true;
    }

    private function checkPatronLimit(): void
    {
        $existing = Patron::where('barcode', $this->barcode)->first();
        if ($existing && $existing->hasReachedLimit()) {
            $this->limitReached = true;
            $this->limitUntil   = $existing->nextAvailableDate()?->format('F j, Y');
        }
    }

    private function finishAfterResolution(): void
    {
        $patron = app(PatronService::class)->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ])['patron'];

        $this->saveRequest($patron);
    }

    private function saveRequest(Patron $patron, ?array $isbndbData = null): void
    {
        $this->processing = true;
        $this->processingStep = 'Saving your request...';

        // Resolve or create material (with ISBNdb enrichment when accepted).
        $material = $this->findOrCreateMaterial(
            [
                'title'                   => $this->title,
                'author'                  => $this->author,
                'publish_date'            => $this->publish_date ?: null,
                'material_type_option_id' => $this->material_type_id,
            ],
            $isbndbData,
        );

        // Determine if duplicate — any prior request for this material counts,
        // including re-submissions from the same patron.
        $priorRequest = PatronRequest::where('material_id', $material->id)->first();

        $pendingStatus = RequestStatus::where('applies_to_sfp', true)->orderBy('sort_order')->first()
            ?? RequestStatus::orderBy('sort_order')->firstOrFail();

        $patronRequest = PatronRequest::create([
            'patron_id'              => $patron->id,
            'material_id'            => $material->id,
            'request_status_id'      => $pendingStatus->id,
            'submitted_title'        => $this->title,
            'submitted_author'       => $this->author,
            'submitted_publish_date' => $this->publish_date ?: null,
            'other_material_text'    => $this->getShowOtherTextProperty() ? $this->other_material_text : null,
            'ill_requested'          => (bool) ($this->custom['ill_requested'] ?? false),
            'notify_by_email'        => $this->notify_by_email,
            'catalog_searched'       => $this->catalogSearched,
            'catalog_result_count'   => count($this->catalogResults),
            'catalog_match_accepted' => $this->catalogMatchAccepted,
            'catalog_match_bib_id'   => $this->catalogMatchBibId,
            'isbndb_searched'        => $this->isbndbSearched,
            'isbndb_result_count'    => count($this->isbndbResults),
            'isbndb_match_accepted'  => $this->isbndbMatchAccepted,
            'is_duplicate'           => (bool) $priorRequest,
            'duplicate_of_request_id'=> $priorRequest?->id,
        ]);

        // Store core field values as EAV
        $coreFieldValues = [
            'material_type' => $this->material_type_id ? (FieldOption::find($this->material_type_id)?->slug) : null,
            'audience'      => $this->audience_id ? (FieldOption::find($this->audience_id)?->slug) : null,
        ];

        // Auto-include any other core fields that have a matching string property
        foreach ($this->formFields as $f) {
            if (isset($coreFieldValues[$f->key])) {
                continue;
            }
            if (property_exists($this, $f->key) && is_string($this->{$f->key}) && $this->{$f->key} !== '') {
                $coreFieldValues[$f->key] = $this->{$f->key};
            }
        }

        foreach ($coreFieldValues as $key => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            $fieldId = Field::where('key', $key)->value('id');
            if ($fieldId) {
                RequestFieldValue::create([
                    'request_id' => $patronRequest->id,
                    'field_id'   => $fieldId,
                    'value'      => (string) $val,
                ]);
            }
        }

        // Store additional custom field values
        $rows = [];
        foreach ($this->stepTwoCustomFields as $field) {
            $val = $this->custom[$field->key] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $rows[] = [
                'request_id'  => $patronRequest->id,
                'field_id'    => $field->id,
                'value'       => is_bool($val) ? ($val ? '1' : '0') : (string) $val,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        if (! empty($rows)) {
            RequestFieldValue::insert($rows);
        }

        // Log initial status history
        $patronRequest->statusHistory()->create([
            'request_status_id' => $pendingStatus->id,
            'user_id' => null,
            'note' => 'Request submitted by patron.',
        ]);

        // Send staff routing notification
        app(NotificationService::class)->notifyStaffNewRequest($patronRequest);

        $this->createdRequestId = $patronRequest->id;
        $this->processing = false;
        $this->step = 4; // Confirmation
    }

    public function render()
    {
        $visible = $this->visibleFields;

        $sfpForm   = Form::bySlug(PatronRequest::KIND_SFP);
        $sfpFormId = $sfpForm?->id;

        $customFieldIds = $this->stepTwoCustomFields->pluck('id')->all();
        $customFieldOptions = [];
        foreach ($customFieldIds as $cfId) {
            $customFieldOptions[$cfId] = $this->formFilteredOptions($cfId, $sfpFormId)
                ->pluck('name', 'slug')
                ->all();
        }

        $mtField    = Field::where('key', 'material_type')->first();
        $audField   = Field::where('key', 'audience')->first();
        $genreField = Field::where('key', 'genre')->first();

        // Build option maps for core select/radio fields without dedicated Blade variables
        $coreFieldOptions = [];
        foreach ($this->formFields as $f) {
            if (in_array($f->type, ['select', 'radio'], true)
                && ! in_array($f->key, ['material_type', 'audience', 'genre'], true)) {
                $coreFieldOptions[$f->id] = $this->formFilteredOptionMap($f->id, $sfpFormId);
            }
        }

        $customFieldVisibility = $this->buildVisibilityMap($this->stepTwoCustomFields);
        $visibleStepTwoCustomFields = $this->stepTwoCustomFields
            ->filter(fn ($field) => $customFieldVisibility[$field->key] ?? false)
            ->values();

        return view('requests::livewire.request-form', [
            'materialTypes'         => $mtField ? $this->formFilteredOptions($mtField->id, $sfpFormId) : collect(),
            'audiences'             => $audField ? $this->formFilteredOptions($audField->id, $sfpFormId) : collect(),
            'genres'                => $genreField ? $this->formFilteredOptions($genreField->id, $sfpFormId) : collect(),
            'coreFieldOptions'      => $coreFieldOptions,
            'orderedFields'         => $this->formFields,
            'visibleStepTwoCustomFields' => $visibleStepTwoCustomFields,
            'customFieldOptionsByFieldId' => $customFieldOptions,
            'visibleFields'     => $visible,             // ['genre' => true/false, ...]
            'successMessage'    => Setting::get('submission_success_message', 'Thank you for your suggestion!'),
            'catalogOwnedMessage' => Setting::get(
                'catalog_owned_message',
                '<p><strong>Good news:</strong> this item is already in our catalog. Please place a hold in the catalog to get it as soon as it\'s available.</p>'
            ),
            'autoOrderExcludedMessage' => $this->autoOrderExcludedMessage,
            'duplicateMessage'  => $this->duplicateMessage,
            'formatLabels'      => CatalogFormatLabel::map(),
        ]);
    }

    /**
     * Return true when a submission should be excluded because the library
     * auto-orders new releases from the author AND the item is not yet released.
     */
    private function shouldAutoOrderExclude(string $author, ?string $releaseDate): bool
    {
        if (! $this->isConfidentFutureDate($releaseDate)) {
            return false;
        }

        $raw = (string) Setting::get('auto_order_author_exclusions', '');
        $list = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        $submitted = $this->parseAuthorName($author);
        if (! $submitted) {
            return false;
        }

        foreach ($list as $entry) {
            $excluded = $this->parseAuthorName((string) $entry);
            if (! $excluded) {
                continue;
            }

            // Match by last name + first initial (covers "Patterson, James" vs "James Patterson").
            if (
                $submitted['last'] !== ''
                && $excluded['last'] !== ''
                && $submitted['last'] === $excluded['last']
                && $submitted['first_initial'] !== ''
                && $excluded['first_initial'] !== ''
                && $submitted['first_initial'] === $excluded['first_initial']
            ) {
                return true;
            }

            // Fallback: exact normalized full-name match in either order.
            if (
                $submitted['normalized_full'] !== ''
                && $excluded['normalized_full'] !== ''
                && (
                    $submitted['normalized_full'] === $excluded['normalized_full']
                    || $submitted['normalized_full'] === $excluded['normalized_swapped']
                    || $submitted['normalized_swapped'] === $excluded['normalized_full']
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Only return true when we can confidently say the date is in the future.
     * Accepts unambiguous formats: YYYY-MM-DD, YYYY-MM, YYYY.
     */
    private function isConfidentFutureDate(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $now = now();

        // ISO datetime strings: 2026-04-15T00:00:00Z → treat as YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) === 1) {
            $value = substr($value, 0, 10);
        }

        // Full date (most reliable): 2026-04-15
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value)->isAfter($now);
            } catch (\Throwable) {
                return false;
            }
        }

        // Year-month: 2026-04 (confidently future if after current month)
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            try {
                $dt = Carbon::createFromFormat('Y-m', $value)->startOfMonth();
                return $dt->isAfter($now->copy()->startOfMonth());
            } catch (\Throwable) {
                return false;
            }
        }

        // Year: 2027 (confidently future only if strictly greater than current year)
        if (preg_match('/^\d{4}$/', $value) === 1) {
            return (int) $value > (int) $now->year;
        }

        // Common human formats the form suggests: "January 2027", "Jan 2027", "January 5 2027"
        // Only treat as confident if it contains a 4-digit year.
        if (preg_match('/\b\d{4}\b/', $value) === 1) {
            try {
                return Carbon::parse($value)->isAfter($now);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Parse an author string into normalized name parts for matching.
     *
     * Returns null if it can't determine a usable name.
     *
     * @return array{first:string,last:string,first_initial:string,normalized_full:string,normalized_swapped:string}|null
     */
    private function parseAuthorName(string $author): ?array
    {
        $author = strtolower(trim($author));
        if ($author === '') {
            return null;
        }

        // If multiple authors are present, only consider the first.
        $author = preg_split('/\s+(and|&)\s+|;/', $author)[0] ?? $author;
        $author = trim($author);

        // Keep letters/numbers/spaces/commas; drop punctuation.
        $author = preg_replace('/[^a-z0-9\s,]/', ' ', $author) ?? $author;
        $author = preg_replace('/\s+/', ' ', $author) ?? $author;
        $author = trim($author);

        if ($author === '') {
            return null;
        }

        $first = '';
        $last = '';

        if (str_contains($author, ',')) {
            // "Last, First Middle"
            [$lastPart, $firstPart] = array_pad(explode(',', $author, 2), 2, '');
            $last = trim($lastPart);
            $first = trim($firstPart);
        } else {
            // "First Middle Last"
            $parts = preg_split('/\s+/', $author) ?: [];
            if (count($parts) >= 2) {
                $first = (string) ($parts[0] ?? '');
                $last = (string) end($parts);
            } else {
                // Single token is too ambiguous to match safely.
                return null;
            }
        }

        $first = trim($first);
        $last = trim($last);

        if ($first === '' || $last === '') {
            return null;
        }

        $firstInitial = $first[0] ?? '';

        $normalizedFull = trim(preg_replace('/\s+/', ' ', "{$first} {$last}") ?? "{$first} {$last}");
        $normalizedSwapped = trim(preg_replace('/\s+/', ' ', "{$last} {$first}") ?? "{$last} {$first}");

        return [
            'first' => $first,
            'last' => $last,
            'first_initial' => $firstInitial,
            'normalized_full' => $normalizedFull,
            'normalized_swapped' => $normalizedSwapped,
        ];
    }
}
