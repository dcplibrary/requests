<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Processing overlay --}}
    @if($processing)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/70">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-6 py-5 max-w-md w-full mx-4">
                <p class="text-sm font-semibold text-gray-900">Please wait…</p>
                <p class="text-sm text-gray-600 mt-1">{{ $processingStep }}</p>
            </div>
        </div>
    @endif

    {{-- Step 1: Patron --}}
    @if($step === 1)
    <section aria-labelledby="patron-heading">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Interlibrary Loan Request</h1>
        <p class="text-sm text-gray-600 mb-6">Request items the library doesn’t own from other libraries.</p>

        <div class="space-y-5 bg-white rounded-lg border border-gray-200 p-6">
            <div>
                <label for="barcode" class="block text-sm font-medium text-gray-700 mb-1">Library Barcode Number <span class="text-red-600">*</span></label>
                <input type="text" id="barcode" wire:model="barcode"
                       class="w-full max-w-xs rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('barcode') ? 'border-red-500' : 'border-gray-300' }}" />
                @error('barcode')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name_first" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-600">*</span></label>
                    <input type="text" id="name_first" wire:model="name_first"
                           class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('name_first') ? 'border-red-500' : 'border-gray-300' }}" />
                    @error('name_first')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="name_last" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-600">*</span></label>
                    <input type="text" id="name_last" wire:model="name_last"
                           class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('name_last') ? 'border-red-500' : 'border-gray-300' }}" />
                    @error('name_last')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-600">*</span></label>
                <input type="tel" id="phone" wire:model="phone"
                       class="w-full max-w-xs rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('phone') ? 'border-red-500' : 'border-gray-300' }}" />
                @error('phone')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" wire:model="email"
                       class="w-full max-w-sm rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 border-gray-300" />
                @error('email')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" wire:click="nextStep"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                Continue
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </section>

    {{-- Step 2: Borrow details --}}
    @elseif($step === 2)
    <section aria-labelledby="details-heading">
        <h2 id="details-heading" class="text-2xl font-bold text-gray-900 mb-6">Borrow Details</h2>

        @if($limitReached)
            <div class="mb-6">
                <x-requests::limit-reached
                    kind="ill"
                    :count="$limitCount"
                    :until="$limitUntil ? \Illuminate\Support\Carbon::parse($limitUntil) : null"
                />
            </div>
        @endif

        <div class="space-y-6 bg-white rounded-lg border border-gray-200 p-6">
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">I want to borrow <span class="text-red-600" aria-hidden="true">*</span></legend>
                <div class="space-y-1" role="radiogroup" aria-label="Type of material" aria-required="true">
                    @foreach($illMaterialTypes as $type)
                        <label class="flex items-center gap-3 p-2 rounded-md cursor-pointer hover:bg-gray-50 {{ $material_type_id == $type->id ? 'bg-blue-50 ring-1 ring-blue-200' : '' }}">
                            <input type="radio" wire:model.live="material_type_id" value="{{ $type->id }}"
                                   name="ill_material_type" class="text-blue-600 focus:ring-blue-500" />
                            <span class="text-sm text-gray-800">{{ $type->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('material_type_id')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            @foreach($orderedFields as $field)
                @php
                    $isVisible = $visibleFields[$field->key] ?? false;
                    $displayLabel = $displayLabels[$field->key] ?? $field->label;
                @endphp
                {{-- wire:key keeps morphdom stable; hidden suppresses invisible fields without removing them from DOM --}}
                <div wire:key="field-{{ $field->key }}" @if(! $isVisible) hidden @endif>
                @if($isVisible)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $displayLabel }}
                            @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                        </label>

                        @if(in_array($field->type, ['radio'], true))
                            @php
                                $radioOptions = match ($field->key ?? '') {
                                    'audience' => $audienceOptions ?? [],
                                    'genre' => $genreOptions ?? [],
                                    default => $optionsByFieldId[$field->id] ?? [],
                                };
                            @endphp
                            <div class="space-y-1" role="radiogroup">
                                @foreach($radioOptions as $slug => $name)
                                    <label class="flex items-center gap-3 p-2 rounded-md cursor-pointer hover:bg-gray-50 {{ ($custom[$field->key] ?? '') === $slug ? 'bg-blue-50 ring-1 ring-blue-200' : '' }}">
                                        <input type="radio" wire:model.live="custom.{{ $field->key }}" value="{{ $slug }}"
                                               class="text-blue-600 focus:ring-blue-500" />
                                        <span class="text-sm text-gray-800">{{ $name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif(in_array($field->type, ['select'], true))
                            <select wire:model.live="custom.{{ $field->key }}"
                                    class="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm">
                                <option value="">Select…</option>
                                @foreach(($optionsByFieldId[$field->id] ?? []) as $slug => $name)
                                    <option value="{{ $slug }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        @elseif($field->type === 'textarea')
                            <textarea wire:model="custom.{{ $field->key }}" rows="4"
                                      class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
                                      placeholder="{{ $field->key === 'other_specify' ? 'Please describe what you need...' : '' }}"></textarea>
                        @elseif($field->type === 'date')
                            <input type="date" wire:model="custom.{{ $field->key }}"
                                   class="w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        @elseif($field->type === 'number')
                            <input type="number" step="0.01" wire:model="custom.{{ $field->key }}"
                                   class="w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        @elseif($field->type === 'checkbox')
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="custom.{{ $field->key }}"
                                       class="rounded text-blue-600 focus:ring-blue-500" />
                                Yes
                            </label>
                        @else
                            <input type="text" wire:model="custom.{{ $field->key }}"
                                   class="w-full max-w-xl rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        @endif

                        @error('custom.' . $field->key)
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-between">
            <button type="button" wire:click="prevStep"
                    class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
                Back
            </button>
            <button type="button" wire:click="submit"
                    class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
                Submit Request
            </button>
        </div>
    </section>

    {{-- Step 3: Catalog and/or ISBNdb resolution --}}
    @elseif($step === 3)
    <section aria-labelledby="resolution-heading">
        @if(count($catalogResults) > 0)
        <h2 id="resolution-heading" class="text-2xl font-bold text-gray-900 mb-4">Before we request from other libraries…</h2>
        <p class="text-sm text-gray-600 mb-6">We found possible matches in our catalog. If we already own it, you can place a hold instead of requesting ILL.</p>

        <div class="space-y-4">
            @foreach($catalogResults as $result)
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $result['title'] ?? 'Match' }}</p>
                            <p class="text-sm text-gray-600">{{ $result['author'] ?? '' }}</p>
                            <p class="text-xs text-gray-400 font-mono mt-1">{{ $result['format'] ?? '' }}</p>
                        </div>
                        <button type="button" wire:click="acceptCatalogMatch('{{ $result['bib_id'] ?? '' }}')"
                                class="shrink-0 px-4 py-2 bg-emerald-600 text-white text-sm rounded hover:bg-emerald-700">
                            We already have this
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-between">
            <button type="button" wire:click="prevStep"
                    class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
                Back
            </button>
            <button type="button" wire:click="skipCatalogMatch"
                    class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
                Continue with ILL request
            </button>
        </div>

        @elseif(count($isbndbResults) > 0 && $isbndbMatchAccepted === null)
        <h2 id="resolution-heading" class="text-2xl font-bold text-gray-900 mb-4">Verify your book details</h2>
        <p class="text-sm text-gray-600 mb-6">We found possible matches. Confirm the correct edition so we can add ISBN and other details to your request.</p>

        <div class="space-y-4">
            @foreach($isbndbResults as $i => $result)
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex gap-3 min-w-0">
                            @if(!empty($result['cover_url']))
                                <img src="{{ $result['cover_url'] }}" alt="" class="w-12 h-auto object-contain rounded shrink-0" />
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ $result['title'] ?? 'Match' }}</p>
                                <p class="text-sm text-gray-600">{{ $result['author_string'] ?? '' }}</p>
                                @if(!empty($result['publisher']) || !empty($result['publish_date']))
                                    <p class="text-xs text-gray-400 mt-1">{{ $result['publisher'] ?? '' }}{{ ($result['publisher'] ?? '') && ($result['publish_date'] ?? '') ? ' · ' : '' }}{{ $result['publish_date'] ?? '' }}</p>
                                @endif
                                @if(!empty($result['isbn13']))
                                    <p class="text-xs text-gray-400 font-mono">ISBN {{ $result['isbn13'] }}</p>
                                @endif
                            </div>
                        </div>
                        <button type="button" wire:click="acceptIsbndbMatch({{ $i }})"
                                class="shrink-0 px-4 py-2 bg-emerald-600 text-white text-sm rounded hover:bg-emerald-700">
                            Yes, this is it
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-between">
            <button type="button" wire:click="prevStep"
                    class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
                Back
            </button>
            <button type="button" wire:click="skipIsbndbMatch"
                    class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
                None of these — continue with my details
            </button>
        </div>
        @endif
    </section>

    {{-- Step 4: Confirmation --}}
    @else
    <section aria-labelledby="confirm-heading">
        <h2 id="confirm-heading" class="text-2xl font-bold text-gray-900 mb-4">Thanks!</h2>

        @if($catalogMatchBibId)
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-5">
                {!! $catalogOwnedMessage !!}
                @if($catalogFoundUrl)
                    <p class="mt-3">
                        <a href="{{ $catalogFoundUrl }}" target="_blank" rel="noopener noreferrer"
                           class="text-sm font-medium text-blue-700 hover:underline">
                            Open catalog record →
                        </a>
                    </p>
                @endif
            </div>
        @else
            <div class="bg-emerald-50 border border-emerald-100 rounded-lg p-5">
                <p class="text-sm text-emerald-900">Your interlibrary loan request has been submitted.</p>
            </div>
        @endif
    </section>
    @endif
</div>

