<div class="max-w-2xl mx-auto px-4 py-8" x-data>

    {{-- Step progress indicator --}}
    @if($step < 4)
    <nav aria-label="Form progress" class="mb-8">
        <ol class="flex items-center gap-2" role="list">
            @foreach([1 => 'Your Info', 2 => 'Item Details', 3 => 'Confirm'] as $num => $label)
            <li class="flex items-center gap-2 {{ $loop->last ? '' : 'flex-1' }}">
                <span
                    class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-semibold border-2 transition-colors
                        {{ $step === $num ? 'bg-blue-600 border-blue-600 text-white' : ($step > $num ? 'bg-green-600 border-green-600 text-white' : 'border-gray-300 text-gray-400') }}"
                    aria-current="{{ $step === $num ? 'step' : false }}"
                >
                    @if($step > $num)
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @else
                        {{ $num }}
                    @endif
                </span>
                <span class="text-sm {{ $step === $num ? 'font-semibold text-blue-600' : 'text-gray-500' }}">{{ $label }}</span>
                @unless($loop->last)
                    <div class="flex-1 h-0.5 {{ $step > $num ? 'bg-green-400' : 'bg-gray-200' }} mx-2" aria-hidden="true"></div>
                @endunless
            </li>
            @endforeach
        </ol>
    </nav>
    @endif

    {{-- Processing overlay --}}
    @if($processing)
    <div class="flex flex-col items-center justify-center py-16 gap-4" role="status" aria-live="polite">
        <svg class="animate-spin w-10 h-10 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        <p class="text-gray-600 text-sm">{{ $processingStep }}</p>
    </div>

    {{-- Step 1: Patron Information --}}
    @elseif($step === 1)
    <section aria-labelledby="patron-heading">
        <h2 id="patron-heading" class="text-2xl font-bold text-gray-900 mb-6">Patron Information</h2>

        <div class="space-y-5">
            {{-- Barcode --}}
            <div>
                <label for="barcode" class="block text-sm font-medium text-gray-700 mb-1">
                    Library Card Barcode Number <span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <input
                    type="text"
                    id="barcode"
                    wire:model="barcode"
                    class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('barcode') || $barcodeNotFound ? 'border-red-500' : 'border-gray-300' }}"
                    autocomplete="off"
                    aria-required="true"
                    aria-describedby="{{ $errors->has('barcode') ? 'barcode-error' : ($barcodeNotFound ? 'barcode-not-found' : '') }}"
                />
                @error('barcode')
                    <p id="barcode-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                @if($barcodeNotFound)
                    <div id="barcode-not-found"
                         class="mt-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 prose prose-sm max-w-none [&_a]:text-red-700 [&_a]:underline"
                         role="alert"
                         aria-live="assertive">
                        {!! $barcodeNotFoundMessage !!}
                    </div>
                @endif
            </div>

            {{-- Name --}}
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-1">
                    Name <span class="text-red-600" aria-hidden="true">*</span>
                </legend>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="name_first" class="sr-only">First name</label>
                        <input
                            type="text"
                            id="name_first"
                            wire:model="name_first"
                            placeholder="First"
                            autocomplete="given-name"
                            class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('name_first') ? 'border-red-500' : 'border-gray-300' }}"
                            aria-required="true"
                            aria-label="First name"
                        />
                        @error('name_first')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="name_last" class="sr-only">Last name</label>
                        <input
                            type="text"
                            id="name_last"
                            wire:model="name_last"
                            placeholder="Last"
                            autocomplete="family-name"
                            class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('name_last') ? 'border-red-500' : 'border-gray-300' }}"
                            aria-required="true"
                            aria-label="Last name"
                        />
                        @error('name_last')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </fieldset>

            {{-- Phone --}}
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                    Phone <span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <input
                    type="tel"
                    id="phone"
                    wire:model="phone"
                    autocomplete="tel"
                    class="w-full max-w-xs rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('phone') ? 'border-red-500' : 'border-gray-300' }}"
                    aria-required="true"
                />
                @error('phone')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input
                    type="email"
                    id="email"
                    wire:model="email"
                    autocomplete="email"
                    class="w-full max-w-sm rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 border-gray-300"
                />
                @error('email')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-8 flex justify-end">
            <button
                type="button"
                wire:click="nextStep"
                class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
                Continue
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        {{-- ── PIN Login ─────────────────────────────────────────────────────────── --}}
        <div class="mt-6 pt-5 border-t border-gray-100 text-center" x-data="{ open: false }">
            <p class="text-sm text-gray-500 mb-2">Want to check on a previous suggestion?</p>
            <button
                type="button"
                @click="open = true"
                class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
            >
                Sign in with your library card PIN →
            </button>

            {{-- Modal --}}
            <div
                x-show="open"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="pin-modal-title"
            >
                {{-- Backdrop --}}
                <div class="absolute inset-0 bg-black/40" @click="open = false" aria-hidden="true"></div>

                {{-- Dialog panel --}}
                <div class="relative bg-white rounded-lg shadow-xl p-6 w-full max-w-sm mx-4 z-10">
                    <h2 id="pin-modal-title" class="text-lg font-semibold text-gray-900 mb-4">
                        Sign In to View Your Requests
                    </h2>
                    @livewire('requests-patron-pin-login')
                </div>
            </div>
        </div>

    </section>

    {{-- Step 2: Material Details --}}
    @elseif($step === 2)
    <section aria-labelledby="material-heading">
        <h2 id="material-heading" class="text-2xl font-bold text-gray-900 mb-6">Material Details</h2>

        @if($limitReached)
            <x-requests::limit-reached
                :count="(int) \Dcplibrary\Requests\Models\Setting::get('sfp_limit_count', 5)"
                :until="$limitUntil ? \Illuminate\Support\Carbon::parse($limitUntil) : null"
            />
        @else
        {{--
            Fields are rendered in the order defined in sfp_form_fields (sort_order).
            Each field only appears when its 'active' flag is on and its condition passes.
            The $visibleFields map (key => bool) is computed by SfpForm::getVisibleFieldsProperty().
        --}}
        <div class="space-y-6">
        @foreach($orderedFields as $field)
        @php $isVisible = $visibleFields[$field->key] ?? false; @endphp

        {{-- ── material_type ─────────────────────────────────── --}}
        @if($field->key === 'material_type' && $isVisible)
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </legend>
                <div class="space-y-1" role="radiogroup" aria-required="{{ $field->required ? 'true' : 'false' }}">
                    @foreach($materialTypes as $type)
                    <label class="flex items-center gap-3 p-2 rounded-md cursor-pointer hover:bg-gray-50 {{ $material_type_id == $type->id ? 'bg-blue-50 ring-1 ring-blue-200' : '' }}">
                        <input
                            type="radio"
                            wire:model.live="material_type_id"
                            value="{{ $type->id }}"
                            name="material_type"
                            class="text-blue-600 focus:ring-blue-500"
                        />
                        <span class="text-sm text-gray-800">{{ $type->name }}</span>
                        @if($type->has_other_text && $material_type_id == $type->id)
                            <input
                                type="text"
                                wire:model="other_material_text"
                                placeholder="Please specify"
                                class="ml-2 flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                aria-label="Please specify material type"
                            />
                        @endif
                    </label>
                    @endforeach
                </div>
                @error('material_type_id')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

        {{-- ── audience ───────────────────────────────────────── --}}
        @elseif($field->key === 'audience' && $isVisible)
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </legend>
                <div class="space-y-1" role="radiogroup" aria-required="{{ $field->required ? 'true' : 'false' }}">
                    @foreach($audiences as $audience)
                    <label class="flex items-center gap-3 p-2 rounded-md cursor-pointer hover:bg-gray-50 {{ $audience_id == $audience->id ? 'bg-blue-50 ring-1 ring-blue-200' : '' }}">
                        <input
                            type="radio"
                            wire:model.live="audience_id"
                            value="{{ $audience->id }}"
                            name="audience"
                            class="text-blue-600 focus:ring-blue-500"
                        />
                        <span class="text-sm text-gray-800">{{ $audience->name }}</span>
                    </label>
                    @endforeach
                </div>
                @error('audience_id')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

        {{-- ── genre ──────────────────────────────────────────── --}}
        @elseif($field->key === 'genre' && $isVisible)
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </legend>
                <div class="flex items-center gap-4 flex-wrap" role="radiogroup" aria-required="{{ $field->required ? 'true' : 'false' }}">
                    @foreach($genres as $genreOption)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="genre" value="{{ $genreOption->slug }}" name="genre" class="text-blue-600 focus:ring-blue-500" />
                        <span class="text-sm text-gray-800">{{ $genreOption->name }}</span>
                    </label>
                    @endforeach
                </div>
                @error('genre')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

        {{-- ── title ───────────────────────────────────────────── --}}
        @elseif($field->key === 'title' && $isVisible)
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </label>
                <input
                    type="text"
                    id="title"
                    wire:model="title"
                    class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('title') ? 'border-red-500' : 'border-gray-300' }}"
                    @if($field->required) aria-required="true" @endif
                />
                @error('title')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

        {{-- ── author ──────────────────────────────────────────── --}}
        @elseif($field->key === 'author' && $isVisible)
            <div>
                <label for="author" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </label>
                <input
                    type="text"
                    id="author"
                    wire:model="author"
                    class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('author') ? 'border-red-500' : 'border-gray-300' }}"
                    @if($field->required) aria-required="true" @endif
                />
                @error('author')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

        {{-- ── isbn ────────────────────────────────────────────── --}}
        @elseif($field->key === 'isbn' && $isVisible)
            <div>
                <label for="isbn" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </label>
                <input
                    type="text"
                    id="isbn"
                    wire:model="isbn"
                    placeholder="e.g. 9780593315163"
                    class="w-full max-w-xs rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('isbn') ? 'border-red-500' : 'border-gray-300' }}"
                    @if($field->required) aria-required="true" @endif
                />
                <p class="mt-1 text-xs text-gray-400">10 or 13 digits, no dashes required</p>
                @error('isbn')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

        {{-- ── publish_date ────────────────────────────────────── --}}
        @elseif($field->key === 'publish_date' && $isVisible)
            <div>
                <label for="publish_date" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </label>
                <input
                    type="text"
                    id="publish_date"
                    wire:model.live="publish_date"
                    placeholder="e.g. 2022 or January 2022"
                    class="w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                @if($showIllWarning)
                <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-md" role="alert">
                    <p class="text-sm text-amber-800">
                        <strong>Note:</strong> {!! $illWarningMessage !!}
                        <a href="https://www.dcplibrary.org/interlibrary-loan/" target="_blank" rel="noopener" class="underline font-medium">Learn more about ILL</a>.
                    </p>
                </div>
                @endif
            </div>

        @endif
        @endforeach

        {{-- SFP custom fields (where_heard textarea, console select, etc.) --}}
        @foreach($stepTwoCustomFields as $field)
            @if(!$this->customFieldVisible($field->key)) @continue @endif
            <div>
                <label for="custom_{{ $field->key }}" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $field->label }}
                    @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                </label>
                @if($field->type === 'textarea')
                    <textarea
                        id="custom_{{ $field->key }}"
                        wire:model="custom.{{ $field->key }}"
                        rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
                    ></textarea>
                @elseif($field->type === 'radio')
                    <div class="flex flex-wrap gap-4" role="radiogroup">
                        @foreach($customFieldOptionsByFieldId[$field->id] ?? [] as $slug => $name)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="custom.{{ $field->key }}" value="{{ $slug }}" class="text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm text-gray-800">{{ $name }}</span>
                            </label>
                        @endforeach
                    </div>
                @elseif($field->type === 'select')
                    <select id="custom_{{ $field->key }}" wire:model.live="custom.{{ $field->key }}"
                            class="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select…</option>
                        @foreach($customFieldOptionsByFieldId[$field->id] ?? [] as $slug => $name)
                            <option value="{{ $slug }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @elseif($field->type === 'html')
                    @once
                        @push('head')
                            <link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
                            <script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
                        @endpush
                    @endonce
                    <input type="hidden"
                           id="custom_{{ $field->key }}_input"
                           value="{{ $this->custom[$field->key] ?? '' }}">
                    <trix-editor
                        input="custom_{{ $field->key }}_input"
                        class="trix-content border border-gray-300 rounded bg-white text-sm"
                        style="min-height: 8rem"
                        x-data
                        x-on:trix-change="$wire.set('custom.{{ $field->key }}', $event.target.value)"
                    ></trix-editor>
                @elseif($field->type === 'checkbox')
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="custom.{{ $field->key }}" value="1"
                               class="rounded text-blue-600 focus:ring-blue-500" />
                        <span class="text-sm text-gray-700">{{ $field->key === 'ill_requested' ? 'Yes, please try interlibrary loan' : 'Yes' }}</span>
                    </label>
                @else
                    <input type="text"
                        id="custom_{{ $field->key }}"
                        wire:model="custom.{{ $field->key }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                @endif
                @error('custom.' . $field->key)
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
        </div>

        <div class="mt-8 flex justify-between">
            <button
                type="button"
                wire:click="prevStep"
                class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back
            </button>
            <button
                type="button"
                wire:click="submit"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition-colors"
            >
                <span wire:loading.remove wire:target="submit">Submit Request</span>
                <span wire:loading wire:target="submit">Searching...</span>
            </button>
        </div>
        @endif
    </section>

    {{-- Step 3: Resolution (catalog / ISBNdb match) --}}
    @elseif($step === 3)
    <section aria-labelledby="resolution-heading">
        <h2 id="resolution-heading" class="text-2xl font-bold text-gray-900 mb-2">We Found Some Results</h2>

        {{-- Duplicate notice --}}
        @if($isDuplicate)
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-md" role="alert">
            <div class="text-sm text-blue-800 prose prose-sm">
                {!! $duplicateMessage !!}
            </div>
        </div>
        @endif

        {{-- Catalog results --}}
        @if(count($catalogResults) > 0 && $catalogMatchAccepted === null)
        <div>
            <p class="text-sm text-gray-600 mb-4">We found the following in our catalog. If one of these is your item, you can place a hold (no suggestion will be submitted).</p>
            <ul class="space-y-3" role="list">
                @foreach($catalogResults as $result)
                <li class="p-4 border border-gray-200 rounded-md bg-white shadow-sm">
                    <div class="flex justify-between items-start gap-3">
                        <div class="flex gap-3">
                            @if($result['cover_url'])
                            <img src="{{ $result['cover_url'] }}" alt="Cover of {{ $result['title'] }}" class="w-12 h-auto object-contain rounded shrink-0" />
                            @endif
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">{{ $result['title'] }}</p>
                                <p class="text-gray-600 text-sm">{{ $result['author'] }}</p>
                                <p class="text-gray-400 text-xs mt-1">{{ $formatLabels[$result['format']] ?? $result['format'] }}{{ $result['year'] ? ' · ' . $result['year'] : '' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 shrink-0">
                            <button
                                type="button"
                                wire:click="acceptCatalogMatch('{{ $result['bib_id'] }}')"
                                class="shrink-0 px-3 py-1 text-xs font-medium bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                            >Yes, this is it</button>
                            <a
                                href="{{ $result['catalog_url'] }}"
                                target="_blank"
                                rel="noopener"
                                class="text-xs text-blue-600 underline focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                            >View in catalog</a>
                        </div>
                    </div>
                </li>
                @endforeach
            </ul>
            <div class="mt-4">
                <button
                    type="button"
                    wire:click="skipCatalogMatch"
                    class="text-sm text-gray-500 underline hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                >None of these are the exact item — continue to submit my suggestion</button>
            </div>
        </div>

        {{-- ISBNdb results --}}
        @elseif(count($isbndbResults) > 0 && $isbndbMatchAccepted === null)
        <div>
            <p class="text-sm text-gray-600 mb-4">We found the following possible matches. Is one of these the item you're looking for?</p>
            <ul class="space-y-3" role="list">
                @foreach($isbndbResults as $i => $result)
                <li class="p-4 border border-gray-200 rounded-md bg-white shadow-sm">
                    <div class="flex justify-between items-start gap-3">
                        <div class="flex gap-3">
                            @if($result['cover_url'])
                            <img src="{{ $result['cover_url'] }}" alt="Cover of {{ $result['title'] }}" class="w-12 h-auto object-contain rounded shrink-0" />
                            @endif
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">{{ $result['title'] }}</p>
                                <p class="text-gray-600 text-sm">{{ $result['author_string'] }}</p>
                                <p class="text-gray-400 text-xs mt-1">
                                    {{ $result['publisher'] ?? '' }}{{ ($result['publisher'] && $result['publish_date']) ? ' · ' : '' }}{{ $result['publish_date'] ?? '' }}
                                </p>
                                @if($result['isbn13'])
                                    <p class="text-gray-400 text-xs">ISBN: {{ $result['isbn13'] }}</p>
                                @endif
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="acceptIsbndbMatch({{ $i }})"
                            class="shrink-0 px-3 py-1 text-xs font-medium bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >Yes, this is it</button>
                    </div>
                </li>
                @endforeach
            </ul>
            <div class="mt-4">
                <button
                    type="button"
                    wire:click="skipIsbndbMatch"
                    class="text-sm text-gray-500 underline hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                >None of these match — submit anyway</button>
            </div>
        </div>

        @else
        {{-- Fallback: duplicate only (no catalog/ISBNdb results), or no results at all --}}
        @if(! $isDuplicate)
        <p class="text-sm text-gray-600 mb-6">We couldn't find an existing match. Your request will be submitted as entered.</p>
        @endif
        <button
            type="button"
            wire:click="skipIsbndbMatch"
            class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >{{ $isDuplicate ? 'Submit Anyway' : 'Submit My Request' }}</button>
        @endif
    </section>

    {{-- Step 4: Confirmation --}}
    @elseif($step === 4)
    <section aria-labelledby="confirmation-heading" class="text-center py-8">
        <div class="flex justify-center mb-4">
            <span class="flex items-center justify-center w-16 h-16 bg-green-100 rounded-full" aria-hidden="true">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
        </div>
        @if($autoOrderExcluded)
            <h2 id="confirmation-heading" class="text-2xl font-bold text-gray-900 mb-3">No request needed</h2>
            <div class="text-gray-600 text-sm max-w-md mx-auto prose prose-sm">
                {!! $autoOrderExcludedMessage !!}
            </div>
        @elseif($catalogMatchAccepted === true)
            <h2 id="confirmation-heading" class="text-2xl font-bold text-gray-900 mb-3">It’s already in the catalog</h2>
            <div class="text-gray-600 text-sm max-w-md mx-auto prose prose-sm">
                {!! $catalogOwnedMessage !!}
            </div>

            @if($catalogFoundUrl)
                <div class="mt-6">
                    <a
                        href="{{ $catalogFoundUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >Open catalog record</a>
                </div>
            @endif
        @else
            <h2 id="confirmation-heading" class="text-2xl font-bold text-gray-900 mb-3">Request Submitted</h2>
            <div class="text-gray-600 text-sm max-w-md mx-auto prose prose-sm">
                {!! $successMessage !!}
            </div>
        @endif

        <div class="mt-8">
            <button
                type="button"
                wire:click="submitAnotherRequest"
                class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            >Submit Another Request</button>
        </div>
    </section>
    @endif

</div>
