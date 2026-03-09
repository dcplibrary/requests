<?php

namespace Dcplibrary\Sfp\Livewire;

use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\RequestCustomFieldValue;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Services\BibliocommonsService;
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

    public bool $processing = false;
    public string $processingStep = '';

    public ?int $createdRequestId = null;

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

        $book = MaterialType::where('slug', 'book')->where('active', true)->first();
        if ($book) {
            $this->material_type_id = $book->id;
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
            $this->validate(['material_type_id' => 'required|exists:material_types,id']);
            $this->clearHiddenFields();
            $this->validate($this->buildStepTwoRules());
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    // --- Custom field config ---

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \Dcplibrary\Sfp\Models\CustomField>
     */
    public function getCustomFieldsProperty()
    {
        return CustomField::forKind('ill')->ordered()->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \Dcplibrary\Sfp\Models\CustomField>
     */
    public function getStepTwoFieldsProperty()
    {
        return $this->customFields->where('step', 2)->values();
    }

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
            ->mapWithKeys(fn (CustomField $f) => [$f->key => $f->isVisibleFor($state)])
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
            $visible  = $field->isVisibleFor($state);
            $required = $visible && $field->required;
            // When "Other" material type is selected, title can come from other_specify; don't require title.
            if ($field->key === 'title' && ($state['material_type'] ?? '') === 'other') {
                $required = false;
            }

            $path = "custom.{$field->key}";

            $rules[$path] = match ($field->type) {
                'radio', 'select' => $this->selectRule($field, $required),
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

    private function selectRule(CustomField $field, bool $required): string
    {
        $slugs = CustomFieldOption::query()
            ->where('custom_field_id', $field->id)
            ->active()
            ->ordered()
            ->pluck('slug')
            ->implode(',');

        $base = $required ? 'required' : 'nullable';
        return $slugs ? "{$base}|in:{$slugs}" : $base;
    }

    // --- Submission ---

    public function submit(): void
    {
        $this->validate(['material_type_id' => 'required|exists:material_types,id']);
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
        $this->saveRequest($patron);
    }

    private function saveRequest(Patron $patron): void
    {
        $this->processing = true;
        $this->processingStep = 'Submitting your request...';

        $pendingStatus = RequestStatus::where('slug', 'pending')->first()
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
            $submittedTitle = 'Interlibrary Loan request';
        }

        $req = SfpRequest::create([
            'patron_id'              => $patron->id,
            'material_id'            => null,
            'audience_id'            => null,
            'material_type_id'       => $this->material_type_id,
            'request_status_id'      => $pendingStatus->id,
            'request_kind'           => 'ill',
            'submitted_title'        => $submittedTitle,
            'submitted_author'       => $submittedAuthor ?: '—',
            'submitted_publish_date' => $submittedPublishDate,
            'other_material_text'    => $materialSlug === 'other' ? $otherSpecify : null,
            'genre'                  => null,
            'where_heard'            => isset($this->custom['where_heard']) ? (string) $this->custom['where_heard'] : null,
            'ill_requested'          => true,
            'catalog_searched'       => $this->catalogSearched,
            'catalog_result_count'   => is_array($this->catalogResults) ? count($this->catalogResults) : null,
            'catalog_match_accepted' => $this->catalogMatchBibId ? true : null,
            'catalog_match_bib_id'   => $this->catalogMatchBibId,
            'isbndb_searched'        => false,
            'isbndb_result_count'    => null,
            'isbndb_match_accepted'  => null,
            'is_duplicate'           => false,
            'duplicate_of_request_id'=> null,
        ]);

        // Persist dynamic custom values.
        $rows = [];
        foreach ($this->stepTwoFields as $field) {
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
     * Section key for conditional heading before this field (books, photocopy, dvd), or null.
     */
    public function getFieldSectionKey(CustomField $field): ?string
    {
        $slug = $this->materialTypeSlug();

        if (in_array($slug, ['book', 'audiobook'], true)
            && in_array($field->key, ['title', 'author', 'publish_date', 'publisher', 'isbn'], true)) {
            return 'books';
        }
        if (in_array($slug, ['magazine-article', 'newspaper-microfilm'], true)
            && in_array($field->key, ['periodical_title', 'article_author', 'article_title', 'volume_number', 'page_number'], true)) {
            return 'photocopy';
        }
        if ($slug === 'dvd' && in_array($field->key, ['title', 'director', 'cast'], true)) {
            return 'dvd';
        }

        return null;
    }

    /**
     * Display label for field (conditional on material type for title/author).
     */
    public function getDisplayLabelForField(CustomField $field): string
    {
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

    public function render()
    {
        $fields = $this->stepTwoFields;

        // Preload options per field for select/radio rendering.
        $options = CustomFieldOption::query()
            ->whereIn('custom_field_id', $fields->pluck('id')->all())
            ->active()
            ->ordered()
            ->get()
            ->groupBy('custom_field_id')
            ->map(fn ($group) => $group->pluck('name', 'slug')->all())
            ->all();

        $fieldSectionKeys = $fields->mapWithKeys(fn (CustomField $f) => [$f->key => $this->getFieldSectionKey($f)])->all();
        $displayLabels = $fields->mapWithKeys(fn (CustomField $f) => [$f->key => $this->getDisplayLabelForField($f)])->all();

        $illSlugs = ['book', 'audiobook', 'dvd', 'magazine-article', 'newspaper-microfilm', 'other'];

        return view('sfp::livewire.ill-form', [
            'orderedFields' => $fields,
            'visibleFields' => $this->visibleCustomFields,
            'optionsByFieldId' => $options,
            'illMaterialTypes' => MaterialType::whereIn('slug', $illSlugs)->where('active', true)->ordered()->get(),
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

