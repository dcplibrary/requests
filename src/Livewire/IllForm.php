<?php

namespace Dcplibrary\Requests\Livewire;

use Dcplibrary\Requests\Livewire\Concerns\CreatesEnrichedMaterial;
use Dcplibrary\Requests\Livewire\Concerns\EvaluatesFieldConditions;
use Dcplibrary\Requests\Livewire\Concerns\FiltersFormFieldOptions;
use Dcplibrary\Requests\Livewire\Concerns\RemembersPatron;
use Dcplibrary\Requests\Livewire\Concerns\WithCoverService;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\Patron;
use Dcplibrary\Requests\Models\RequestFieldValue;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\Material;
use Dcplibrary\Requests\Services\BibliocommonsService;
use Dcplibrary\Requests\Services\IsbnDbService;
use Dcplibrary\Requests\Services\NotificationService;
use Dcplibrary\Requests\Services\PatronService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Multi-step Interlibrary Loan patron submission form (catalog, ISBNdb, material, submit).
 */
#[Layout('requests::layouts.requests')]
class IllForm extends Component
{
    use CreatesEnrichedMaterial;
    use EvaluatesFieldConditions;
    use FiltersFormFieldOptions;
    use RemembersPatron;
    use WithCoverService;
    public int $step = 1;

    // Step 1 (patron)
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

    // Step 2: material type (FieldOption ID) + dynamic field answers
    public ?int $material_type_id = null;

    /** @var array<string, mixed> */
    public array $custom = [];

    // Set when arriving via redirect from the SFP form — enables compact step-2 view.
    public bool $fromSfp = false;

    /** Keys of custom fields that were pre-filled from the SFP redirect. */
    public array $sfpPrefillKeys = [];

    /** Catalog resolution (Step 3) */
    public array $catalogResults = [];
    public bool $catalogSearched = false;
    public ?string $catalogMatchBibId = null;
    public ?string $catalogFoundUrl = null;

    /** ISBNdb enrichment (Step 3, when catalog had no hits or patron skipped) */
    public array $isbndbResults = [];
    public bool $isbndbSearched = false;
    public ?bool $isbndbMatchAccepted = null;

    public bool $processing = false;
    public string $processingStep = '';

    public ?int $createdRequestId = null;

    /** ILL request limit: set when patron has reached their ILL limit (block submit, show message). */
    public bool $limitReached = false;
    public ?string $limitUntil = null;
    public int $limitCount = 0;

    public function getIllLimitCountProperty(): int
    {
        $raw = Setting::get('ill_limit_count', '');
        $v = trim((string) $raw) === '' ? 0 : (int) $raw;
        return $v > 0 ? $v : 0;
    }

    public function mount(): void
    {
        $this->hydratePatronFromSession();

        // Pre-fill material fields when redirected from the SFP form.
        $prefill = session()->pull('request.ill_prefill');
        if (is_array($prefill)) {
            // Pull out material_type_id separately — it's not a custom field.
            if (!empty($prefill['material_type_id'])) {
                $this->material_type_id = (int) $prefill['material_type_id'];
            }
            unset($prefill['material_type_id']);

            foreach ($prefill as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $this->custom[$key] = (string) $value;
                    $this->sfpPrefillKeys[] = $key;
                }
            }
        }

        // Skip patron step when arriving from the SFP form redirect
        // (patron data is already hydrated from session above).
        if (session()->pull('request.ill_skip_patron', false)) {
            $this->step = 2;
            $this->fromSfp = ! empty($this->sfpPrefillKeys);
        }

        $allowed = $this->getAllowedMaterialTypeIds();
        if (! empty($allowed)) {
            $this->material_type_id = in_array($this->material_type_id, $allowed, true) ? $this->material_type_id : $allowed[0];
        } else {
            $mtField = Field::where('key', 'material_type')->first();
            if ($mtField) {
                $book = FieldOption::where('field_id', $mtField->id)->where('slug', 'book')->where('active', true)->first();
                if ($book) {
                    $this->material_type_id = $book->id;
                }
            }
        }
    }

    private function materialTypeSlug(): ?string
    {
        if ($this->material_type_id === null) {
            return null;
        }
        return FieldOption::find($this->material_type_id)?->slug ?? null;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate($this->patronValidationRules());
        }

        if ($this->step === 2) {
            $this->validate(['material_type_id' => $this->materialTypeIdValidationRule()]);
            $this->clearHiddenFields();
            $this->validate($this->buildStepTwoRules());
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    // --- Custom field config (from ILL form settings: FormCustomField visible, required, conditional_logic) ---

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \Dcplibrary\Requests\Models\Field>
     */
    public function getCustomFieldsProperty()
    {
        return Field::forKind(PatronRequest::KIND_ILL)->ordered()->get();
    }

    /**
     * Step 2 fields for the ILL form from form_field_config, merged and ordered by sort_order.
     *
     * @return \Illuminate\Support\Collection<int, object{key: string, id: int, type: string, label: string, required: bool, condition: array, sort_order: int, field: Field}>
     */
    public function getStepTwoFieldsProperty()
    {
        $form = Form::bySlug(PatronRequest::KIND_ILL);

        if ($form) {
            $configs = $form->fieldConfigs()
                ->with('field')
                ->where('visible', true)
                ->orderBy('sort_order')
                ->get();

            $rows = collect();
            foreach ($configs as $cfg) {
                $f = $cfg->field;
                if (! $f || ! $f->active || $f->key === 'material_type') {
                    continue;
                }
                $hasOverride = $cfg->label_override !== null && $cfg->label_override !== '';
                $rows->push((object) [
                    'key'               => $f->key,
                    'id'                => $f->id,
                    'type'              => $f->type ?? 'text',
                    'label'             => $hasOverride ? $cfg->label_override : $f->label,
                    'has_label_override' => $hasOverride,
                    'required'          => (bool) $cfg->required,
                    'condition'         => $cfg->conditional_logic ?? $f->condition ?? ['match' => 'all', 'rules' => []],
                    'sort_order'        => $cfg->sort_order,
                    'field'             => $f,
                ]);
            }

            if ($rows->isNotEmpty()) {
                return $rows->sortBy('sort_order')->values();
            }
        }

        // Fallback: no ILL form config, use base fields only
        return Field::forKind(PatronRequest::KIND_ILL)->where('step', 2)->active()->ordered()->get()->map(function (Field $f, int $i) {
            return (object) [
                'key'         => $f->key,
                'id'          => $f->id,
                'type'        => $f->type,
                'label'       => $f->label,
                'required'    => (bool) $f->required,
                'condition'   => $f->condition ?? ['match' => 'all', 'rules' => []],
                'sort_order'  => $i,
                'field'       => $f,
            ];
        });
    }

    /**
     * @return array<string, bool>
     */
    public function getVisibleCustomFieldsProperty(): array
    {
        return $this->buildVisibilityMap($this->stepTwoFields);
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

    private function clearHiddenFields(): void
    {
        foreach ($this->stepTwoFields as $field) {
            if (! $this->customFieldVisible($field->key)) {
                unset($this->custom[$field->key]);
            }
        }
    }

    /**
     * Build validation rules for all visible step-two fields.
     *
     * @return array<string, string>
     */
    private function buildStepTwoRules(): array
    {
        $rules  = [];
        $state  = $this->formConditionState();
        $formId = Form::bySlug(PatronRequest::KIND_ILL)?->id;

        foreach ($this->stepTwoFields as $field) {
            $visible  = Field::evaluateCondition($field->condition ?? ['match' => 'all', 'rules' => []], $state);
            $required = $visible && $field->required;
            // When "Other" material type is selected, title can come from other_specify; don't require title.
            if ($field->key === 'title' && ($state['material_type'] ?? '') === 'other') {
                $required = false;
            }

            $path = "custom.{$field->key}";

            if ($field->key === 'publish_date' && $field->type === 'date') {
                $rules[$path] = $required ? 'required|string|max:200' : 'nullable|string|max:200';

                continue;
            }

            $rules[$path] = match ($field->type) {
                'radio', 'select' => $this->selectOrRadioRule($field->field, $required, $formId),
                'text'            => $required ? 'required|string|max:500' : 'nullable|string|max:500',
                'textarea'        => $required ? 'required|string|max:5000' : 'nullable|string|max:5000',
                'date'            => $required ? 'required|date' : 'nullable|date',
                'number'          => $required ? 'required|numeric' : 'nullable|numeric',
                'checkbox'        => 'nullable|boolean',
                default           => $required ? 'required' : 'nullable',
            };
        }

        return $rules;
    }

    // --- Submission ---

    public function submit(): void
    {
        $this->validate(['material_type_id' => $this->materialTypeIdValidationRule()]);
        $this->clearHiddenFields();
        $this->validate($this->buildStepTwoRules());

        $this->processing = true;
        $this->processingStep = 'Saving your information...';

        /** @var Patron $patron */
        $patron = app(PatronService::class)->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ])['patron'];

        if ($patron->hasReachedLimit(PatronRequest::KIND_ILL)) {
            $this->limitReached = true;
            $raw = Setting::get('ill_limit_count', '');
            $this->limitCount   = trim((string) $raw) === '' ? 0 : (int) $raw;
            $this->limitUntil   = $patron->nextAvailableDate(PatronRequest::KIND_ILL)?->format('F j, Y');
            $this->processing   = false;
            return;
        }

        // Lightweight “already owned” check for book/audiobook/dvd material types.
        $materialSlug = $this->materialTypeSlug();
        $title = (string) ($this->custom['title'] ?? '');
        $author = (string) ($this->custom['author'] ?? '');

        if (in_array($materialSlug, ['book', 'audiobook', 'dvd'], true) && $title !== '') {
            $this->processingStep = 'Checking our catalog...';
            $result = app(BibliocommonsService::class)->search($title, $author, 'adult', null);
            $this->catalogSearched = true;
            $this->catalogResults = $result['results'] ?? [];

            if (count($this->catalogResults) > 0) {
                $this->processing = false;
                $this->step = 3;
                return;
            }
        }

        // No catalog hits: optionally search ISBNdb for enrichment (when this material type is searchable)
        $materialTypeOpt = FieldOption::find($this->material_type_id);
        if ($materialTypeOpt?->meta('isbndb_searchable', false) && $title !== '' && Setting::get('ill_isbndb_enabled', false)) {
            $this->processingStep = 'Verifying book details...';
            $service = app(IsbnDbService::class);
            $searchResult = $service->search($title, $author);
            $this->isbndbSearched = true;
            $this->isbndbResults = $this->withCovers($searchResult['results'], 'isbndb');

            if (count($this->isbndbResults) > 0) {
                $this->processing = false;
                $this->step = 3;
                return;
            }
        }

        $this->saveRequest($patron);
    }

    public function acceptCatalogMatch(string $bibId): void
    {
        $this->catalogMatchBibId = $bibId;

        $match = collect($this->catalogResults)->firstWhere('bib_id', $bibId);
        $this->catalogFoundUrl = is_array($match) ? ($match['catalog_url'] ?? null) : null;

        $this->processing = false;
        $this->step = 4;
    }

    public function skipCatalogMatch(): void
    {
        /** @var Patron $patron */
        $patron = Patron::where('barcode', $this->barcode)->firstOrFail();

        // Optionally show ISBNdb results for enrichment (when this material type is searchable)
        $materialTypeOpt = FieldOption::find($this->material_type_id);
        $title = (string) ($this->custom['title'] ?? '');
        $author = (string) ($this->custom['author'] ?? '');

        if ($materialTypeOpt?->meta('isbndb_searchable', false) && ! $this->isbndbSearched && $title !== '' && Setting::get('ill_isbndb_enabled', false)) {
            $this->processingStep = 'Verifying book details...';
            $service = app(IsbnDbService::class);
            $searchResult = $service->search($title, $author);
            $this->isbndbSearched = true;
            $this->isbndbResults = $this->withCovers($searchResult['results'], 'isbndb');

            if (count($this->isbndbResults) > 0) {
                $this->processing = false;
                $this->step = 3;
                return;
            }
        }

        $this->saveRequest($patron);
    }

    public function acceptIsbndbMatch(int $index): void
    {
        /** @var Patron $patron */
        $patron = Patron::where('barcode', $this->barcode)->firstOrFail();
        $isbndbData = $this->isbndbResults[$index] ?? null;
        $this->isbndbMatchAccepted = true;
        $this->saveRequest($patron, is_array($isbndbData) ? $isbndbData : null);
    }

    public function skipIsbndbMatch(): void
    {
        /** @var Patron $patron */
        $patron = Patron::where('barcode', $this->barcode)->firstOrFail();
        $this->saveRequest($patron);
    }

    /**
     * Persist the ILL request, material (when ISBNdb matched), and all field values as EAV.
     *
     * @param  Patron      $patron
     * @param  array|null  $isbndbData  Enrichment data from ISBNdb when a match was accepted.
     * @return void
     */
    private function saveRequest(Patron $patron, ?array $isbndbData = null): void
    {
        if ($patron->hasReachedLimit(PatronRequest::KIND_ILL)) {
            $this->limitReached = true;
            $raw = Setting::get('ill_limit_count', '');
            $this->limitCount   = trim((string) $raw) === '' ? 0 : (int) $raw;
            $this->limitUntil   = $patron->nextAvailableDate(PatronRequest::KIND_ILL)?->format('F j, Y');
            $this->processing   = false;
            return;
        }

        $this->processing = true;
        $this->processingStep = 'Submitting your request...';

        $pendingStatus = RequestStatus::where('applies_to_ill', true)->orderBy('sort_order')->first()
            ?? RequestStatus::orderBy('sort_order')->firstOrFail();

        $materialSlug    = $this->materialTypeSlug();
        $submittedTitle  = trim((string) ($this->custom['title'] ?? ''));
        $submittedAuthor = trim((string) ($this->custom['author'] ?? ''));
        $submittedPublishDate = trim((string) ($this->custom['publish_date'] ?? '')) ?: null;
        $otherSpecify    = trim((string) ($this->custom['other_specify'] ?? ''));

        if ($materialSlug === 'other' && $otherSpecify !== '') {
            $submittedTitle = 'Other: ' . $otherSpecify;
        }
        if ($submittedTitle === '') {
            $submittedTitle = request_form_name(PatronRequest::KIND_ILL) . ' request';
        }

        $materialId = null;
        if ($isbndbData !== null) {
            $material = $this->findOrCreateMaterial(
                [
                    'title'                   => $submittedTitle,
                    'author'                  => $submittedAuthor,
                    'publish_date'            => $submittedPublishDate,
                    'material_type_option_id' => $this->material_type_id,
                ],
                $isbndbData,
            );
            $materialId = $material->id;
        }

        $req = PatronRequest::create([
            'patron_id'               => $patron->id,
            'material_id'             => $materialId,
            'request_status_id'       => $pendingStatus->id,
            'request_kind'            => PatronRequest::KIND_ILL,
            'submitted_title'         => $submittedTitle,
            'submitted_author'        => $submittedAuthor ?: '—',
            'submitted_publish_date'  => $submittedPublishDate,
            'other_material_text'     => $materialSlug === 'other' ? $otherSpecify : null,
            'notify_by_email'         => $this->notify_by_email,
            'ill_requested'           => true,
            'catalog_searched'        => $this->catalogSearched,
            'catalog_result_count'    => is_array($this->catalogResults) ? count($this->catalogResults) : null,
            'catalog_match_accepted'  => $this->catalogMatchBibId ? true : null,
            'catalog_match_bib_id'    => $this->catalogMatchBibId,
            'isbndb_searched'         => $this->isbndbSearched,
            'isbndb_result_count'     => count($this->isbndbResults),
            'isbndb_match_accepted'   => $isbndbData !== null,
            'is_duplicate'            => false,
            'duplicate_of_request_id' => null,
        ]);

        // Persist all field values (material type + step-two fields) via EAV.
        $rows = [];

        $mtField = Field::where('key', 'material_type')->first();
        if ($mtField && $this->material_type_id) {
            $mtOption = FieldOption::find($this->material_type_id);
            if ($mtOption) {
                $rows[] = [
                    'request_id' => $req->id,
                    'field_id'   => $mtField->id,
                    'value'      => $mtOption->slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach ($this->stepTwoFields as $field) {
            $val = $this->custom[$field->key] ?? null;
            if ($val === null || $val === '') {
                continue;
            }

            $rows[] = [
                'request_id' => $req->id,
                'field_id'   => $field->id,
                'value'      => is_bool($val) ? ($val ? '1' : '0') : (string) $val,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($rows)) {
            RequestFieldValue::insert($rows);
        }

        $req->statusHistory()->create([
            'request_status_id' => $pendingStatus->id,
            'user_id'           => null,
            'note'              => 'ILL request submitted by patron.',
        ]);

        $this->savePatronToSession();

        app(NotificationService::class)->notifyStaffNewRequest($req);

        $this->createdRequestId = $req->id;
        $this->processing = false;
        $this->step = 4;
    }

    /**
     * Section key for conditional heading before this field (books, photocopy, dvd), or null.
     *
     * @param  object{key: string}  $field  CustomField or step-two wrapper
     */
    public function getFieldSectionKey(object $field): ?string
    {
        $slug = $this->materialTypeSlug();
        $key  = $field->key;

        if (in_array($slug, ['book', 'audiobook'], true)
            && in_array($key, ['title', 'author', 'publish_date', 'publisher', 'isbn'], true)) {
            return 'books';
        }
        if ($slug === 'dvd'
            && in_array($key, ['title', 'director', 'cast'], true)) {
            return 'dvd';
        }
        if ($slug === 'magazine-article'
            && in_array($key, ['title', 'author', 'publish_date'], true)) {
            return 'magazine';
        }
        if ($slug === 'magazine-article'
            && in_array($key, ['periodical_title', 'volume_number', 'page_number'], true)) {
            return 'photocopy';
        }
        if ($slug === 'newspaper-microfilm'
            && in_array($key, ['title', 'author', 'publish_date'], true)) {
            return 'newspaper';
        }
        if ($slug === 'newspaper-microfilm'
            && in_array($key, ['periodical_title', 'volume_number', 'page_number'], true)) {
            return 'photocopy';
        }
        if ($slug === 'other'
            && in_array($key, ['title', 'author', 'publish_date'], true)) {
            return 'other-material';
        }

        return null;
    }

    /**
     * Display label for field (conditional on material type for title/author).
     *
     * If the admin set a label_override on the form field pivot, that always wins.
     * Otherwise, context-sensitive defaults are used for title/author.
     *
     * @param  object{key: string, label: string}  $field  CustomField or step-two wrapper
     */
    public function getDisplayLabelForField(object $field): string
    {
        // Admin's label_override always takes precedence.
        if (! empty($field->has_label_override)) {
            return $field->label;
        }

        $slug = $this->materialTypeSlug();

        if ($field->key === 'title') {
            return match ($slug) {
                'book'      => 'Book Title',
                'audiobook' => 'Audiobook Title',
                'dvd'       => 'Movie Title',
                default     => $field->label,
            };
        }
        if ($field->key === 'author') {
            return match ($slug) {
                'book'      => 'Author of Book',
                'audiobook' => 'Author of Audiobook',
                default     => $field->label,
            };
        }

        return $field->label;
    }


    /**
     * Material type options for the ILL form (objects with id, name, slug).
     *
     * @return \Illuminate\Support\Collection<int, object{id: int, name: string, slug: string}>
     */
    private function getIllMaterialTypesOptions(): \Illuminate\Support\Collection
    {
        $mtField = Field::where('key', 'material_type')->first();
        if (! $mtField) {
            return collect();
        }

        $form = Form::bySlug(PatronRequest::KIND_ILL);

        return $this->formFilteredOptions($mtField->id, $form?->id);
    }

    /**
     * Allowed material type IDs for this form (for validation).
     *
     * @return array<int, int>
     */
    private function getAllowedMaterialTypeIds(): array
    {
        return $this->getIllMaterialTypesOptions()->pluck('id')->all();
    }

    /**
     * Validation rule for material_type_id (FieldOption ID).
     *
     * @return string
     */
    private function materialTypeIdValidationRule(): string
    {
        $allowed = $this->getAllowedMaterialTypeIds();

        return empty($allowed) ? 'required|exists:field_options,id' : 'required|in:' . implode(',', $allowed);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        $fields = $this->stepTwoFields;
        $form   = Form::bySlug(PatronRequest::KIND_ILL);

        // Build options for all select/radio fields from the unified field_options table.
        $options = [];
        foreach ($fields as $f) {
            if (in_array($f->type, ['select', 'radio'], true)) {
                $options[$f->id] = $this->formFilteredOptionMap($f->id, $form?->id);
            }
        }

        $fieldSectionKeys = $fields->mapWithKeys(fn ($f) => [$f->key => $this->getFieldSectionKey($f)])->all();
        $displayLabels    = $fields->mapWithKeys(fn ($f) => [$f->key => $this->getDisplayLabelForField($f)])->all();

        // Material type objects (for the "I want to borrow" selector).
        $illMaterialTypes = $this->getIllMaterialTypesOptions();
        $mtBySlug         = $illMaterialTypes->keyBy('slug');

        // Audience / genre options extracted from the unified map for backward-compat with Blade templates.
        $audienceId = $fields->firstWhere('key', 'audience')?->id;
        $genreId    = $fields->firstWhere('key', 'genre')?->id;

        return view('requests::livewire.ill-form', [
            'orderedFields'       => $fields,
            'step1CustomKeys'     => ['prefer_email'], // collected in patron step, skip in step 2
            'visibleFields'       => $this->visibleCustomFields,
            'optionsByFieldId'    => $options,
            'audienceOptions'     => $audienceId ? ($options[$audienceId] ?? []) : [],
            'genreOptions'        => $genreId ? ($options[$genreId] ?? []) : [],
            'illMaterialTypes'    => $illMaterialTypes,
            'fieldSectionKeys'    => $fieldSectionKeys,
            'sectionLabels'       => [
                'books'          => 'Books',
                'photocopy'      => 'Photocopy/Microfilm',
                'dvd'            => $mtBySlug->get('dvd')?->name ?? 'DVD/VHS',
                'magazine'       => $mtBySlug->get('magazine-article')?->name ?? 'Magazine Article',
                'newspaper'      => $mtBySlug->get('newspaper-microfilm')?->name ?? 'Newspaper/Microfilm',
                'other-material' => $mtBySlug->get('other')?->name ?? 'Other',
            ],
            'displayLabels'       => $displayLabels,
            'catalogOwnedMessage' => Setting::get(
                'catalog_owned_message',
                '<p><strong>Good news:</strong> this item is already in our catalog. Please place a hold in the catalog to get it as soon as it\'s available.</p>'
            ),
        ]);
    }
}

