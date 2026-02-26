<?php

namespace Dcplibrary\Sfp\Livewire;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\Material;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\RequestStatus;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Services\BibliocommonsService;
use Dcplibrary\Sfp\Services\IsbnDbService;
use Dcplibrary\Sfp\Services\PatronService;
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
    #[Validate('required|exists:material_types,id')]
    public ?int $material_type_id = null;

    public string $other_material_text = '';

    #[Validate('required|exists:audiences,id')]
    public ?int $audience_id = null;

    #[Validate('required|min:1|max:500')]
    public string $title = '';

    #[Validate('required|min:1|max:300')]
    public string $author = '';

    public string $publish_date = '';

    public string $where_heard = '';

    public bool $ill_requested = false;

    // --- ILL age warning ---
    public bool $showIllWarning = false;

    // --- Step 3: Resolution state ---
    public ?int $resolvedMaterialId = null; // set if local material match found
    public bool $isDuplicate = false;
    public string $duplicateMessage = '';

    // Catalog results passed to view
    public array $catalogResults = [];
    public bool $catalogSearched = false;
    public ?string $catalogMatchBibId = null;

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

    public function mount(): void
    {
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
            $this->validate([
                'barcode'    => 'required|min:5|max:20',
                'name_first' => 'required|min:1|max:100',
                'name_last'  => 'required|min:1|max:100',
                'phone'      => 'required|min:7|max:20',
                'email'      => 'nullable|email|max:255',
            ]);
            $this->checkPatronLimit();
        }

        if ($this->step === 2) {
            $this->validate([
                'material_type_id' => 'required|exists:material_types,id',
                'audience_id'      => 'required|exists:audiences,id',
                'title'            => 'required|min:1|max:500',
                'author'           => 'required|min:1|max:300',
            ]);
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    // --- ILL age warning (triggered by publish_date change) ---

    public function updatedPublishDate(string $value): void
    {
        $this->showIllWarning = Material::yearExceedsIllThreshold($value);
    }

    // --- Material type "Other" toggle ---

    public function getShowOtherTextProperty(): bool
    {
        if (! $this->material_type_id) {
            return false;
        }
        $type = MaterialType::find($this->material_type_id);
        return $type?->has_other_text ?? false;
    }

    // --- Main submission ---

    public function submit(): void
    {
        $this->validate();

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
            $window = Setting::get('sfp_limit_window', 'day');
            $count = Setting::get('sfp_limit_count', 5);
            $this->addError('barcode', "You have reached the limit of {$count} requests per {$window}. Please try again later.");
            $this->processing = false;
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
            $this->catalogResults = $result['results'];

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
            $this->isbndbResults = $result['results'];

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
        $this->finishAfterResolution();
    }

    public function skipCatalogMatch(): void
    {
        $this->catalogMatchAccepted = false;

        // Move on to ISBNdb if not yet searched
        if (! $this->isbndbSearched && Setting::get('isbndb_search_enabled', true)) {
            $service = app(IsbnDbService::class);
            $result = $service->search($this->title, $this->author);
            $this->isbndbSearched = true;
            $this->isbndbResults = $result['results'];

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
            $window = Setting::get('sfp_limit_window', 'day');
            $count = Setting::get('sfp_limit_count', 5);
            $this->addError('barcode', "You have reached the limit of {$count} requests per {$window}. Please try again later.");
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
            'other_material_text'    => $this->getShowOtherTextProperty() ? $this->other_material_text : null,
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

        $this->createdRequestId = $sfpRequest->id;
        $this->processing = false;
        $this->step = 4; // Confirmation
    }

    public function render()
    {
        return view('sfp::livewire.sfp-form', [
            'materialTypes'     => MaterialType::active()->get(),
            'audiences'         => Audience::active()->get(),
            'illWarningMessage' => Setting::get('ill_warning_message', ''),
            'successMessage'    => Setting::get('submission_success_message', 'Thank you for your suggestion!'),
            'duplicateMessage'  => $this->duplicateMessage,
        ]);
    }
}
