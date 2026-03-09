<?php

namespace Dcplibrary\Sfp\Livewire;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\CatalogFormatLabel;
use Dcplibrary\Sfp\Models\Console;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\Genre;
use Dcplibrary\Sfp\Models\Material;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Services\BibliocommonsService;
use Dcplibrary\Sfp\Services\CoverService;
use Dcplibrary\Sfp\Services\IsbnDbService;
use Dcplibrary\Sfp\Services\PatronService;
use Dcplibrary\Sfp\Services\NotificationService;
use Dcplibrary\Sfp\Services\PolarisService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('sfp::layouts.sfp')]
class SfpForm extends Component
{
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

    public string $where_heard = '';

    public bool $ill_requested = false;

    // --- ILL age warning ---
    public bool $showIllWarning = false;

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

    public function mount(): void
    {
        // If the patron previously submitted a request and chose "Submit Another Request",
        // we keep their patron info in the session for convenience.
        $remembered = session('request.patron');
        if (is_array($remembered)) {
            $this->barcode     = (string) ($remembered['barcode'] ?? $this->barcode);
            $this->name_first  = (string) ($remembered['name_first'] ?? $this->name_first);
            $this->name_last   = (string) ($remembered['name_last'] ?? $this->name_last);
            $this->phone       = (string) ($remembered['phone'] ?? $this->phone);
            $this->email       = (string) ($remembered['email'] ?? $this->email);
        }

        // Pre-select "Book" as default material type
        $book = MaterialType::where('slug', 'book')->where('active', true)->first();
        if ($book) {
            $this->material_type_id = $book->id;
        }

        // Pre-select "Adult" as default audience
        $adult = Audience::where('slug', 'adult')->where('active', true)->first();
        if ($adult) {
            $this->audience_id = $adult->id;
        }
    }

    // --- Step navigation ---

    public function nextStep(): void
    {
        if ($this->step === 1) {
            // Reset barcode-not-found state on each attempt
            $this->barcodeNotFound = false;
            $this->barcodeNotFoundMessage = '';

            $this->validate([
                'barcode'    => 'required|min:5|max:20',
                'name_first' => 'required|min:1|max:100',
                'name_last'  => 'required|min:1|max:100',
                'phone'      => 'required|min:7|max:20',
                'email'      => 'nullable|email|max:255',
            ]);

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
        session()->put('request.patron', [
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email,
        ]);

        // Reset item + resolution state only (keep patron fields)
        $this->reset([
            'title',
            'author',
            'publish_date',
            'where_heard',
            'ill_requested',
            'showIllWarning',
            'other_material_text',
            'genre',
            'console',
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

        // Clear "other" text when it isn't currently shown.
        if (! $this->showOtherText) {
            $this->other_material_text = '';
        }
    }

    private function clearFieldValue(string $key): void
    {
        match ($key) {
            'genre'        => $this->genre = '',
            'console'      => $this->console = '',
            'title'        => $this->title = '',
            'author'       => $this->author = '',
            'isbn'         => $this->isbn = '',
            'publish_date' => $this->publish_date = '',
            'where_heard'  => $this->where_heard = '',
            'ill_requested'=> $this->ill_requested = false,
            default        => null,
        };
    }

    // --- ILL age warning (triggered by publish_date change) ---

    public function updatedPublishDate(string $value): void
    {
        $this->showIllWarning = Material::yearExceedsIllThreshold($value);
    }

    // --- Material type "Other" toggle (inline text within material type radio row) ---

    public function getShowOtherTextProperty(): bool
    {
        if (! $this->material_type_id) {
            return false;
        }
        $type = MaterialType::find($this->material_type_id);
        return $type?->has_other_text ?? false;
    }

    // --- Form field config (cached) ---

    /**
     * Return all form fields ordered, keyed by their 'key'.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Dcplibrary\Sfp\Models\FormField>
     */
    public function getFormFieldsProperty()
    {
        return FormField::allOrdered();
    }

    /**
     * Current form state used to evaluate conditional logic.
     * Maps the condition rule 'field' names to the currently selected slug.
     */
    private function formState(): array
    {
        $materialSlug = $this->material_type_id
            ? (MaterialType::find($this->material_type_id)?->slug ?? '')
            : '';

        $audienceSlug = $this->audience_id
            ? (Audience::find($this->audience_id)?->slug ?? '')
            : '';

        return [
            'material_type' => $materialSlug,
            'audience'      => $audienceSlug,
        ];
    }

    /**
     * Return the set of field keys that are visible given the current form state.
     *
     * @return array<string, bool>  ['genre' => true, 'console' => false, ...]
     */
    public function getVisibleFieldsProperty(): array
    {
        $state = $this->formState();

        return $this->formFields
            ->mapWithKeys(fn (FormField $f) => [$f->key => $f->isVisibleFor($state)])
            ->all();
    }

    /**
     * True when the given field key is both active and passes its condition.
     */
    public function fieldVisible(string $key): bool
    {
        return $this->visibleFields[$key] ?? false;
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
            'material_type_id' => 'required|exists:material_types,id',
            'audience_id'      => 'required|exists:audiences,id',
        ];

        $fieldRuleMap = [
            'genre'        => function (bool $req) {
                $slugs = Genre::active()->pluck('slug')->implode(',');
                return $req ? "required|in:$slugs" : "nullable|in:$slugs";
            },
            'console'      => function (bool $req) {
                $slugs = Console::active()->pluck('slug')->implode(',');
                return $req ? "required|in:$slugs" : "nullable|in:$slugs";
            },
            'title'        => fn (bool $req) => $req ? 'required|min:1|max:500' : 'nullable|min:1|max:500',
            'author'       => fn (bool $req) => $req ? 'required|min:1|max:300' : 'nullable|min:1|max:300',
            'isbn'         => fn (bool $req) => $req ? 'required|string|max:20' : 'nullable|string|max:20',
            'publish_date' => fn ()           => 'nullable|string|max:50',
            'where_heard'  => fn ()           => 'nullable|string|max:1000',
            'ill_requested'=> fn ()           => 'nullable|boolean',
        ];

        $state = $this->formState();

        foreach ($this->formFields as $field) {
            if (! isset($fieldRuleMap[$field->key])) {
                continue;
            }

            $required = $field->isRequiredFor($state);

            // Map field key → Livewire property name (console has its own property now)
            $prop = $field->key;

            $rules[$prop] = $fieldRuleMap[$field->key]($required);
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
            $priorRequest = SfpRequest::where('material_id', $existingMaterial->id)->first();
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
            $audience = Audience::find($this->audience_id);
            $service = app(BibliocommonsService::class);
            $result = $service->search(
                $this->title,
                $this->author,
                $audience?->bibliocommons_value ?? 'adult',
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
        if (! $this->isDuplicate && Setting::get('isbndb_search_enabled', true)) {
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

        // 6. Save request (no match found, or duplicate case)
        $this->saveRequest($patron);
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
        if (! $this->isbndbSearched && Setting::get('isbndb_search_enabled', true)) {
            $service = app(IsbnDbService::class);
            $result = $service->search($this->title, $this->author);
            $this->isbndbSearched = true;
            $this->isbndbResults = $this->withCovers($result['results'], 'isbndb');

            if (count($this->isbndbResults) > 0) {
                return; // Stay on step 3, show ISBNdb results
            }
        }

        $patron = app(PatronService::class)->findOrCreate([
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email ?: null,
        ])['patron'];

        $this->saveRequest($patron);
    }

    public function acceptIsbndbMatch(int $index): void
    {
        $this->isbndbMatchAccepted = true;
        $this->selectedIsbndbIndex = $index;

        $isbndbData = $this->isbndbResults[$index] ?? null;

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

        $this->saveRequest($patron);
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

        // Resolve or create material
        $material = Material::findMatch($this->title, $this->author);

        if (! $material) {
            $materialData = [
                'title'           => $this->title,
                'author'          => $this->author,
                'publish_date'    => $this->publish_date ?: null,
                'material_type_id'=> $this->material_type_id,
                'source'          => 'submitted',
            ];

            if ($isbndbData) {
                $materialData = array_merge($materialData, [
                    'isbn'             => $isbndbData['isbn'] ?? null,
                    'isbn13'           => $isbndbData['isbn13'] ?? null,
                    'publisher'        => $isbndbData['publisher'] ?? null,
                    'exact_publish_date' => isset($isbndbData['publish_date']) ? date('Y-m-d', strtotime($isbndbData['publish_date'])) : null,
                    'edition'          => $isbndbData['edition'] ?? null,
                    'overview'         => $isbndbData['overview'] ?? null,
                    'source'           => 'isbndb',
                ]);
            }

            $material = Material::create($materialData);
        }

        // Determine if duplicate — any prior request for this material counts,
        // including re-submissions from the same patron.
        $priorRequest = SfpRequest::where('material_id', $material->id)->first();

        $pendingStatus = RequestStatus::where('slug', 'pending')->first()
            ?? RequestStatus::orderBy('sort_order')->firstOrFail();

        $sfpRequest = SfpRequest::create([
            'patron_id'              => $patron->id,
            'material_id'            => $material->id,
            'audience_id'            => $this->audience_id,
            'material_type_id'       => $this->material_type_id,
            'request_status_id'      => $pendingStatus->id,
            'submitted_title'        => $this->title,
            'submitted_author'       => $this->author,
            'submitted_publish_date' => $this->publish_date ?: null,
            'other_material_text'    => $this->getShowOtherTextProperty()
                                            ? $this->other_material_text
                                            : ($this->fieldVisible('console') ? $this->console : null),
            'genre'                  => $this->genre ?: null,
            'where_heard'            => $this->where_heard ?: null,
            'ill_requested'          => $this->ill_requested,
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

        // Log initial status history
        $sfpRequest->statusHistory()->create([
            'request_status_id' => $pendingStatus->id,
            'user_id' => null,
            'note' => 'Request submitted by patron.',
        ]);

        // Send staff routing notification
        app(NotificationService::class)->notifyStaffNewRequest($sfpRequest);

        $this->createdRequestId = $sfpRequest->id;
        $this->processing = false;
        $this->step = 4; // Confirmation
    }

    /**
     * Decorate search results with a cover_url from CoverService.
     * Source 'catalog' uses isbns[0] + jacket fallback.
     * Source 'isbndb' uses isbn13/isbn + image fallback.
     */
    private function withCovers(array $results, string $source): array
    {
        $covers = app(CoverService::class);

        return array_map(function (array $result) use ($covers, $source) {
            if ($source === 'catalog') {
                $isbn     = $result['isbns'][0] ?? null;
                $fallback = $result['jacket'] ?? null;
            } else {
                $isbn     = $result['isbn13'] ?? $result['isbn'] ?? null;
                $fallback = $result['image'] ?? null;
            }

            $result['cover_url'] = $covers->url($isbn, $fallback);
            return $result;
        }, $results);
    }

    public function render()
    {
        $visible = $this->visibleFields;

        return view('sfp::livewire.sfp-form', [
            'materialTypes'     => MaterialType::active()->get(),
            'audiences'         => Audience::active()->get(),
            'genres'            => Genre::active()->get(),
            'consoles'          => Console::active()->get(),
            'orderedFields'     => $this->formFields,   // FormField collection in sort_order
            'visibleFields'     => $visible,             // ['genre' => true/false, ...]
            'illWarningMessage' => Setting::get('ill_warning_message', ''),
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
