<?php

namespace Dcplibrary\Sfp\Livewire;

use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormCustomField;
use Dcplibrary\Sfp\Models\FormCustomFieldOption;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\FormFormField;
use Dcplibrary\Sfp\Models\FormFormFieldOption;
use Dcplibrary\Sfp\Models\Genre;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\RequestCustomFieldValue;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Models\Material;
use Dcplibrary\Sfp\Services\BibliocommonsService;
use Dcplibrary\Sfp\Services\CoverService;
use Dcplibrary\Sfp\Services\IsbnDbService;
use Dcplibrary\Sfp\Services\NotificationService;
use Dcplibrary\Sfp\Services\PatronService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('sfp::layouts.sfp')]
class IllForm extends Component
{
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

    // Step 2: material type (from material_types table) + dynamic custom field answers
    public ?int $material_type_id = null;

    /** @var array<string, mixed> */
    public array $custom = [];

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
        // When a patron switches from the SFP form (e.g. after age-of-book warning) to ILL,
        // pre-fill from shared request.patron session so they don't re-enter details.
        $remembered = session('request.patron');
        if (is_array($remembered)) {
            $this->barcode    = (string) ($remembered['barcode'] ?? $this->barcode);
            $this->name_first = (string) ($remembered['name_first'] ?? $this->name_first);
            $this->name_last  = (string) ($remembered['name_last'] ?? $this->name_last);
            $this->phone      = (string) ($remembered['phone'] ?? $this->phone);
            $this->email      = (string) ($remembered['email'] ?? $this->email);
        }

        $allowed = $this->getAllowedMaterialTypeIds();
        if (! empty($allowed)) {
            $this->material_type_id = in_array($this->material_type_id, $allowed, true) ? $this->material_type_id : $allowed[0];
        } else {
            $book = MaterialType::where('slug', 'book')->where('active', true)->first();
            if ($book) {
                $this->material_type_id = $book->id;
            }
        }
    }

    private function materialTypeSlug(): ?string
    {
        if ($this->material_type_id === null) {
            return null;
        }
        return MaterialType::find($this->material_type_id)?->slug ?? null;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'barcode'    => 'required|min:5|max:20',
                'name_first' => 'required|min:1|max:100',
                'name_last'  => 'required|min:1|max:100',
                'phone'      => 'required|min:7|max:20',
                'email'      => 'nullable|email|max:255',
            ]);
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
     * @return \Illuminate\Database\Eloquent\Collection<int, \Dcplibrary\Sfp\Models\CustomField>
     */
    public function getCustomFieldsProperty()
    {
        return CustomField::forKind('ill')->ordered()->get();
    }

    /**
     * Step 2 fields for the ILL form: form fields (FormFormField) + custom fields (FormCustomField)
     * from the ILL form settings, merged and ordered by sort_order.
     *
     * @return \Illuminate\Support\Collection<int, object{key: string, id: int, type: string, label: string, required: bool, condition: array, sort_order: int, source: string, customField?: CustomField, formField?: FormField}>
     */
    public function getStepTwoFieldsProperty()
    {
        $form = Form::bySlug('ill');
        $rows = collect();

        if ($form) {
            // Form fields (Title, Author, ISBN, Audience, Genre, etc.) — skip material_type (rendered at top).
            $formPivots = $form->formFormFields()
                ->with('formField')
                ->where('visible', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($formPivots as $pivot) {
                $f = $pivot->formField;
                if (! $f || ! $f->active || $f->key === 'material_type') {
                    continue;
                }
                $hasOverride = $pivot->label_override !== null && $pivot->label_override !== '';
                $rows->push((object) [
                    'key'               => $f->key,
                    'id'                => $f->id,
                    'type'              => $f->type ?? 'text',
                    'label'             => $hasOverride ? $pivot->label_override : $f->label,
                    'has_label_override' => $hasOverride,
                    'required'          => (bool) $pivot->required,
                    'condition'         => $pivot->conditional_logic ?? $f->condition ?? ['match' => 'all', 'rules' => []],
                    'sort_order'        => $pivot->sort_order,
                    'source'            => 'form',
                    'formField'         => $f,
                ]);
            }

            // Custom fields (Date needed by, Will pay, etc.)
            $customPivots = $form->formCustomFields()
                ->with('customField')
                ->where('step', 2)
                ->where('visible', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($customPivots as $pivot) {
                $cf = $pivot->customField;
                if (! $cf || ! $cf->active) {
                    continue;
                }
                $rows->push((object) [
                    'key'         => $cf->key,
                    'id'          => $cf->id,
                    'type'        => $cf->type,
                    'label'       => $pivot->label_override !== null && $pivot->label_override !== '' ? $pivot->label_override : $cf->label,
                    'required'    => (bool) $pivot->required,
                    'condition'   => $pivot->conditional_logic ?? $cf->condition ?? ['match' => 'all', 'rules' => []],
                    'sort_order'  => $pivot->sort_order,
                    'source'      => 'custom',
                    'customField' => $cf,
                ]);
            }

            if ($rows->isNotEmpty()) {
                return $rows->sortBy('sort_order')->values();
            }
        }

        // Fallback: no ILL form config, use base custom fields only
        return CustomField::forKind('ill')->where('step', 2)->active()->ordered()->get()->map(function (CustomField $cf, int $i) {
            return (object) [
                'key'         => $cf->key,
                'id'          => $cf->id,
                'type'        => $cf->type,
                'label'       => $cf->label,
                'required'    => (bool) $cf->required,
                'condition'   => $cf->condition ?? ['match' => 'all', 'rules' => []],
                'sort_order'  => $i,
                'source'      => 'custom',
                'customField' => $cf,
            ];
        });
    }

    /** @deprecated No longer used — field type now comes from sfp_form_fields.type column. */

    /**
     * Current state for conditional logic evaluation: key => selected slug/string.
     *
     * @return array<string, string|null>
     */
    /**
     * Current state for conditional logic: material_type from selection, plus select/radio custom values.
     *
     * @return array<string, string|null>
     */
    private function customState(): array
    {
        $state = [
            'material_type' => $this->materialTypeSlug(),
        ];

        foreach ($this->stepTwoFields as $field) {
            if (! in_array($field->type, ['select', 'radio'], true)) {
                continue;
            }
            $val = $this->custom[$field->key] ?? null;
            $state[$field->key] = is_string($val) ? $val : null;
        }

        return $state;
    }

    /**
     * @return array<string, bool>
     */
    public function getVisibleCustomFieldsProperty(): array
    {
        $state = $this->customState();

        return $this->stepTwoFields
            ->mapWithKeys(fn ($f) => [$f->key => CustomField::evaluateCondition($f->condition, $state)])
            ->all();
    }

    public function customFieldVisible(string $key): bool
    {
        return $this->visibleCustomFields[$key] ?? false;
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
     * @return array<string, string>
     */
    private function buildStepTwoRules(): array
    {
        $rules = [];
        $state = $this->customState();

        foreach ($this->stepTwoFields as $field) {
            $visible  = CustomField::evaluateCondition($field->condition, $state);
            $required = $visible && $field->required;
            // When "Other" material type is selected, title can come from other_specify; don't require title.
            if ($field->key === 'title' && ($state['material_type'] ?? '') === 'other') {
                $required = false;
            }

            $path = "custom.{$field->key}";

            // Form fields (Title, Author, Audience, Genre, etc.) — no customField
            if (isset($field->source) && $field->source === 'form') {
                $rules[$path] = match ($field->type) {
                    'text'  => $required ? 'required|string|max:500' : 'nullable|string|max:500',
                    'date'  => $required ? 'required|date' : 'nullable|date',
                    'radio' => $this->formFieldRadioRule($field->key, $required),
                    default => $required ? 'required|string|max:500' : 'nullable|string|max:500',
                };
                continue;
            }

            $rules[$path] = match ($field->type) {
                'radio', 'select' => $this->customFieldSelectRule($field->customField, $required),
                'text'           => $required ? 'required|string|max:500' : 'nullable|string|max:500',
                'textarea'       => $required ? 'required|string|max:5000' : 'nullable|string|max:5000',
                'date'           => $required ? 'required|date' : 'nullable|date',
                'number'         => $required ? 'required|numeric' : 'nullable|numeric',
                'checkbox'       => 'nullable|boolean',
                default          => $required ? 'required' : 'nullable',
            };
        }

        return $rules;
    }

    /** Custom field select/radio rule using ILL form option visibility when applicable. */
    private function customFieldSelectRule(CustomField $field, bool $required): string
    {
        $options = $this->getIllCustomFieldOptions([$field->id])[$field->id] ?? null;
        $slugs   = $options !== null ? array_keys($options) : CustomFieldOption::query()
            ->where('custom_field_id', $field->id)
            ->active()
            ->ordered()
            ->pluck('slug')
            ->all();
        $base = $required ? 'required' : 'nullable';
        return $slugs !== [] ? $base . '|in:' . implode(',', $slugs) : $base;
    }

    private function formFieldRadioRule(string $key, bool $required): string
    {
        $slugs = match ($key) {
            'audience' => implode(',', array_keys($this->getIllAudienceOptions())),
            'genre'    => implode(',', array_keys($this->getIllGenreOptions())),
            default    => '',
        };
        $base = $required ? 'required' : 'nullable';
        return $slugs !== '' ? "{$base}|in:{$slugs}" : $base;
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

        if ($patron->hasReachedLimit('ill')) {
            $this->limitReached = true;
            $raw = Setting::get('ill_limit_count', '');
            $this->limitCount   = trim((string) $raw) === '' ? 0 : (int) $raw;
            $this->limitUntil   = $patron->nextAvailableDate('ill')?->format('F j, Y');
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
        $materialType = MaterialType::find($this->material_type_id);
        if ($materialType?->isbndb_searchable && $title !== '' && Setting::get('ill_isbndb_enabled', false)) {
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
        $materialType = MaterialType::find($this->material_type_id);
        $title = (string) ($this->custom['title'] ?? '');
        $author = (string) ($this->custom['author'] ?? '');

        if (! $this->isbndbSearched && $materialType?->isbndb_searchable && $title !== '' && Setting::get('ill_isbndb_enabled', false)) {
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

    private function saveRequest(Patron $patron, ?array $isbndbData = null): void
    {
        if ($patron->hasReachedLimit('ill')) {
            $this->limitReached = true;
            $raw = Setting::get('ill_limit_count', '');
            $this->limitCount   = trim((string) $raw) === '' ? 0 : (int) $raw;
            $this->limitUntil   = $patron->nextAvailableDate('ill')?->format('F j, Y');
            $this->processing   = false;
            return;
        }

        $this->processing = true;
        $this->processingStep = 'Submitting your request...';

        $pendingStatus = RequestStatus::where('slug', 'pending')->first()
            ?? RequestStatus::orderBy('sort_order')->firstOrFail();

        $materialSlug    = $this->materialTypeSlug();
        $submittedTitle  = trim((string) ($this->custom['title'] ?? ''));
        $submittedAuthor = trim((string) ($this->custom['author'] ?? ''));
        $submittedPublishDate = trim((string) ($this->custom['publish_date'] ?? '')) ?: null;
        $otherSpecify    = trim((string) ($this->custom['other_specify'] ?? ''));

        $audienceId = null;
        if (! empty($this->custom['audience'])) {
            $audienceId = Audience::where('slug', (string) $this->custom['audience'])->value('id');
        }
        $genreSlug = ! empty($this->custom['genre']) ? trim((string) $this->custom['genre']) : null;

        if ($materialSlug === 'other' && $otherSpecify !== '') {
            $submittedTitle = 'Other: ' . $otherSpecify;
        }
        if ($submittedTitle === '') {
            $submittedTitle = 'Interlibrary Loan request';
        }

        $materialId = null;
        if ($isbndbData !== null) {
            $titleForMatch = $isbndbData['title'] ?? $submittedTitle;
            $authorForMatch = $isbndbData['author_string'] ?? $submittedAuthor;
            $material = Material::findMatch($titleForMatch, $authorForMatch);

            if ($material) {
                $material->update([
                    'isbn'               => $isbndbData['isbn'] ?? $material->isbn,
                    'isbn13'             => $isbndbData['isbn13'] ?? $material->isbn13,
                    'publisher'          => $isbndbData['publisher'] ?? $material->publisher,
                    'exact_publish_date' => isset($isbndbData['publish_date']) ? date('Y-m-d', strtotime($isbndbData['publish_date'])) : $material->exact_publish_date,
                    'edition'            => $isbndbData['edition'] ?? $material->edition,
                    'overview'           => $isbndbData['overview'] ?? $material->overview,
                    'source'             => 'isbndb',
                ]);
            } else {
                $material = Material::create([
                    'title'              => $titleForMatch,
                    'author'             => $authorForMatch,
                    'publish_date'       => $submittedPublishDate,
                    'material_type_id'   => $this->material_type_id,
                    'isbn'               => $isbndbData['isbn'] ?? null,
                    'isbn13'             => $isbndbData['isbn13'] ?? null,
                    'publisher'          => $isbndbData['publisher'] ?? null,
                    'exact_publish_date' => isset($isbndbData['publish_date']) ? date('Y-m-d', strtotime($isbndbData['publish_date'])) : null,
                    'edition'            => $isbndbData['edition'] ?? null,
                    'overview'           => $isbndbData['overview'] ?? null,
                    'source'             => 'isbndb',
                ]);
            }
            $materialId = $material->id;
        }

        $req = SfpRequest::create([
            'patron_id'              => $patron->id,
            'material_id'            => $materialId,
            'audience_id'            => $audienceId,
            'material_type_id'       => $this->material_type_id,
            'request_status_id'      => $pendingStatus->id,
            'request_kind'           => 'ill',
            'submitted_title'        => $submittedTitle,
            'submitted_author'       => $submittedAuthor ?: '—',
            'submitted_publish_date' => $submittedPublishDate,
            'other_material_text'    => $materialSlug === 'other' ? $otherSpecify : null,
            'genre'                  => $genreSlug,
            'where_heard'            => isset($this->custom['where_heard']) ? (string) $this->custom['where_heard'] : null,
            'ill_requested'          => true,
            'catalog_searched'       => $this->catalogSearched,
            'catalog_result_count'   => is_array($this->catalogResults) ? count($this->catalogResults) : null,
            'catalog_match_accepted' => $this->catalogMatchBibId ? true : null,
            'catalog_match_bib_id'   => $this->catalogMatchBibId,
            'isbndb_searched'        => $this->isbndbSearched,
            'isbndb_result_count'    => count($this->isbndbResults),
            'isbndb_match_accepted'  => $isbndbData !== null,
            'is_duplicate'           => false,
            'duplicate_of_request_id'=> null,
        ]);

        // Persist custom field values only (form fields like title/author are on the request).
        $rows = [];
        foreach ($this->stepTwoFields as $field) {
            if (! isset($field->customField)) {
                continue;
            }
            $val = $this->custom[$field->key] ?? null;
            if ($val === null || $val === '') {
                continue;
            }

            $rows[] = [
                'request_id'       => $req->id,
                'custom_field_id'  => $field->id,
                'value_slug'       => in_array($field->type, ['select', 'radio'], true) ? (string) $val : null,
                'value_text'       => in_array($field->type, ['select', 'radio'], true) ? null : (is_bool($val) ? ($val ? '1' : '0') : (string) $val),
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        if (! empty($rows)) {
            RequestCustomFieldValue::insert($rows);
        }

        $req->statusHistory()->create([
            'request_status_id' => $pendingStatus->id,
            'user_id' => null,
            'note' => 'ILL request submitted by patron.',
        ]);

        session()->put('request.patron', [
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email,
        ]);

        app(NotificationService::class)->notifyStaffNewRequest($req);

        $this->createdRequestId = $req->id;
        $this->processing = false;
        $this->step = 4;
    }

    /**
     * Decorate ISBNdb results with cover_url (same pattern as SfpForm).
     */
    private function withCovers(array $results, string $source): array
    {
        $covers = app(CoverService::class);
        return array_map(function (array $result) use ($covers) {
            $isbn = $result['isbn13'] ?? $result['isbn'] ?? null;
            $fallback = $result['image'] ?? null;
            $result['cover_url'] = $covers->url($isbn, $fallback);
            return $result;
        }, $results);
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
        if (in_array($slug, ['magazine-article', 'newspaper-microfilm'], true)
            && in_array($key, ['periodical_title', 'volume_number', 'page_number'], true)) {
            return 'photocopy';
        }
        if ($slug === 'dvd' && in_array($key, ['title', 'director', 'cast'], true)) {
            return 'dvd';
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
     * Material types for "I want to borrow" from ILL form options (FormFormFieldOption).
     * Respects per-form visible, sort_order, and label_override. Fallback: all active material types.
     *
     * @return \Illuminate\Support\Collection<int, object{id: int, name: string}>
     */
    private function getIllMaterialTypesOptions(): \Illuminate\Support\Collection
    {
        $form = Form::bySlug('ill');
        $field = FormField::where('key', 'material_type')->first();
        if (! $form || ! $field) {
            return MaterialType::where('active', true)->ordered()->get()->map(fn ($mt) => (object) ['id' => $mt->id, 'name' => $mt->name]);
        }

        $overrides = FormFormFieldOption::where('form_id', $form->id)
            ->where('form_field_id', $field->id)
            ->get()
            ->keyBy('option_slug');

        $base = MaterialType::where('active', true)->ordered()->get();
        $merged = $base->map(function ($mt, int $i) use ($overrides) {
            $ov = $overrides->get($mt->slug);
            return (object) [
                'id'         => $mt->id,
                'slug'       => $mt->slug,
                'name'       => $ov && $ov->label_override !== null && $ov->label_override !== '' ? $ov->label_override : $mt->name,
                'visible'    => $ov ? (bool) $ov->visible : true,
                'sort_order' => $ov ? $ov->sort_order : ($i + 1) * 10000,
            ];
        });

        return $merged->filter(fn ($o) => $o->visible)->sortBy('sort_order')->values()->map(fn ($o) => (object) ['id' => $o->id, 'name' => $o->name]);
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

    private function materialTypeIdValidationRule(): string
    {
        $allowed = $this->getAllowedMaterialTypeIds();
        return empty($allowed) ? 'required|exists:material_types,id' : 'required|in:' . implode(',', $allowed);
    }

    /**
     * Audience options (slug => name) from ILL form FormFormFieldOption. Fallback: all active.
     *
     * @return array<string, string>
     */
    private function getIllAudienceOptions(): array
    {
        $form = Form::bySlug('ill');
        $field = FormField::where('key', 'audience')->first();
        if (! $form || ! $field) {
            return Audience::orderBy('sort_order')->pluck('name', 'slug')->all();
        }

        $overrides = FormFormFieldOption::where('form_id', $form->id)
            ->where('form_field_id', $field->id)
            ->get()
            ->keyBy('option_slug');

        $base = Audience::orderBy('sort_order')->get();
        $merged = $base->map(function ($a, int $i) use ($overrides) {
            $ov = $overrides->get($a->slug);
            return (object) [
                'slug'       => $a->slug,
                'name'       => $ov && $ov->label_override !== null && $ov->label_override !== '' ? $ov->label_override : $a->name,
                'visible'    => $ov ? (bool) $ov->visible : true,
                'sort_order' => $ov ? $ov->sort_order : ($i + 1) * 10000,
            ];
        });

        return $merged->filter(fn ($o) => $o->visible)->sortBy('sort_order')->pluck('name', 'slug')->all();
    }

    /**
     * Genre options (slug => name) from ILL form FormFormFieldOption. Fallback: all active.
     *
     * @return array<string, string>
     */
    private function getIllGenreOptions(): array
    {
        $form = Form::bySlug('ill');
        $field = FormField::where('key', 'genre')->first();
        if (! $form || ! $field) {
            return Genre::active()->ordered()->pluck('name', 'slug')->all();
        }

        $overrides = FormFormFieldOption::where('form_id', $form->id)
            ->where('form_field_id', $field->id)
            ->get()
            ->keyBy('option_slug');

        $base = Genre::active()->ordered()->get();
        $merged = $base->map(function ($g, int $i) use ($overrides) {
            $ov = $overrides->get($g->slug);
            return (object) [
                'slug'       => $g->slug,
                'name'       => $ov && $ov->label_override !== null && $ov->label_override !== '' ? $ov->label_override : $g->name,
                'visible'    => $ov ? (bool) $ov->visible : true,
                'sort_order' => $ov ? $ov->sort_order : ($i + 1) * 10000,
            ];
        });

        return $merged->filter(fn ($o) => $o->visible)->sortBy('sort_order')->pluck('name', 'slug')->all();
    }

    /**
     * Custom field options for the ILL form with per-form visibility, order, and label overrides.
     *
     * @return array<int, array<string, string>> custom_field_id => [ slug => name ]
     */
    private function getIllCustomFieldOptions(array $customFieldIds): array
    {
        if (empty($customFieldIds)) {
            return [];
        }

        $form = Form::bySlug('ill');
        if (! $form) {
            $base = CustomFieldOption::query()
                ->whereIn('custom_field_id', $customFieldIds)
                ->active()
                ->ordered()
                ->get()
                ->groupBy('custom_field_id');
            return $base->map(fn ($g) => $g->pluck('name', 'slug')->all())->all();
        }

        $overrides = FormCustomFieldOption::where('form_id', $form->id)
            ->whereIn('custom_field_option_id', CustomFieldOption::whereIn('custom_field_id', $customFieldIds)->pluck('id'))
            ->get()
            ->keyBy('custom_field_option_id');

        $baseOptions = CustomFieldOption::query()
            ->whereIn('custom_field_id', $customFieldIds)
            ->active()
            ->ordered()
            ->get();

        $result = [];
        foreach ($baseOptions->groupBy('custom_field_id') as $customFieldId => $opts) {
            $merged = $opts->map(function (CustomFieldOption $opt, int $i) use ($overrides) {
                $ov = $overrides->get($opt->id);
                return (object) [
                    'slug'       => $opt->slug,
                    'name'       => $ov && $ov->label_override !== null && $ov->label_override !== '' ? $ov->label_override : $opt->name,
                    'visible'    => $ov ? (bool) $ov->visible : true,
                    'sort_order' => $ov ? $ov->sort_order : ($i + 1) * 10000,
                ];
            });
            $result[$customFieldId] = $merged->filter(fn ($o) => $o->visible)->sortBy('sort_order')->pluck('name', 'slug')->all();
        }

        return $result;
    }

    public function render()
    {
        $fields = $this->stepTwoFields;

        $customFieldIds = $fields->filter(fn ($f) => isset($f->customField))->pluck('id')->all();
        $options = $this->getIllCustomFieldOptions($customFieldIds);

        $fieldSectionKeys = $fields->mapWithKeys(fn ($f) => [$f->key => $this->getFieldSectionKey($f)])->all();
        $displayLabels = $fields->mapWithKeys(fn ($f) => [$f->key => $this->getDisplayLabelForField($f)])->all();

        return view('sfp::livewire.ill-form', [
            'orderedFields' => $fields,
            'visibleFields' => $this->visibleCustomFields,
            'optionsByFieldId' => $options,
            'audienceOptions' => $this->getIllAudienceOptions(),
            'genreOptions' => $this->getIllGenreOptions(),
            'illMaterialTypes' => $this->getIllMaterialTypesOptions(),
            'fieldSectionKeys' => $fieldSectionKeys,
            'sectionLabels' => [
                'books' => 'Books',
                'photocopy' => 'Photocopy/Microfilm',
                'dvd' => 'DVD',
            ],
            'displayLabels' => $displayLabels,
            'catalogOwnedMessage' => Setting::get(
                'catalog_owned_message',
                '<p><strong>Good news:</strong> this item is already in our catalog. Please place a hold in the catalog to get it as soon as it\'s available.</p>'
            ),
        ]);
    }
}

