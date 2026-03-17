<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Shows as soon as Livewire sends the request (catalog/ISBNdb run before any server-rendered overlay) --}}
    <div wire:loading.flex wire:target="submit"
         class="fixed inset-0 z-[60] items-center justify-center bg-white/85 backdrop-blur-sm"
         role="status"
         aria-live="polite"
         aria-busy="true">
        <div class="bg-white rounded-xl border border-gray-200 shadow-xl px-8 py-6 max-w-md mx-4 text-center">
            <svg class="animate-spin h-9 w-9 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-base font-semibold text-gray-900">Working on your request…</p>
            <p class="text-sm text-gray-600 mt-2 leading-relaxed">We may search our catalog and other sources. That can take 30 seconds or more — please keep this page open.</p>
        </div>
    </div>

    {{-- Processing overlay (second phase after first response, e.g. saving) --}}
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
    <x-requests::patron-step
        :title="request_form_name('ill') . ' Request'"
        subtitle="Request items the library doesn't own from other libraries."
        :show-notify-by-email="true"
        notify-wire-model="custom.prefer_email"
    />

        <div class="mt-6 flex justify-end">
            <button type="button" wire:click="nextStep"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                Continue
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

    {{-- Step 2: Borrow details --}}
    @elseif($step === 2)
    <section aria-labelledby="details-heading">

        @if($fromSfp)
        {{-- ── Compact mode: arriving from SFP redirect ── --}}
        <h2 id="details-heading" class="text-2xl font-bold text-gray-900 mb-1">Almost done!</h2>
        <p class="text-sm text-gray-500 mb-6">We have your item details — just fill in a few extra fields for the ILL request.</p>

        @if($limitReached)
            <div class="mb-6">
                <x-requests::limit-reached
                    kind="ill"
                    :count="$limitCount"
                    :until="$limitUntil ? \Illuminate\Support\Carbon::parse($limitUntil) : null"
                />
            </div>
        @endif

        {{-- Item summary --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 flex items-start gap-3">
            <svg class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.75 16.82A7.462 7.462 0 0 1 15 15.5c.71 0 1.396.098 2.046.282A.75.75 0 0 0 18 15.06v-11a.75.75 0 0 0-.546-.721A9.006 9.006 0 0 0 15 3a8.963 8.963 0 0 0-4.25 1.065V16.82ZM9.25 4.065A8.963 8.963 0 0 0 5 3c-.85 0-1.673.118-2.454.339A.75.75 0 0 0 2 4.06v11a.75.75 0 0 0 .954.721A7.506 7.506 0 0 1 5 15.5c1.579 0 3.042.487 4.25 1.32V4.065Z"/>
            </svg>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Requesting via ILL</p>
                <p class="font-semibold text-gray-900 text-sm leading-snug">{{ $custom['title'] ?? '' }}</p>
                @if(!empty($custom['author']))
                    <p class="text-sm text-gray-600 mt-0.5">{{ $custom['author'] }}</p>
                @endif
                @if(!empty($custom['publish_date']))
                    <p class="text-xs text-gray-400 mt-0.5">{{ $custom['publish_date'] }}</p>
                @endif
            </div>
        </div>

        {{-- Extra ILL fields only (skip prefilled core fields) --}}
        <div class="space-y-6 bg-white rounded-lg border border-gray-200 p-6">
            @foreach($orderedFields as $field)
                @php
                    $isVisible = $visibleFields[$field->key] ?? false;
                    $isPrefilled = in_array($field->key, $sfpPrefillKeys, true);
                    $displayLabel = $displayLabels[$field->key] ?? $field->label;
                @endphp
                @if(! $isPrefilled)
                <div wire:key="field-{{ $field->key }}" @if(! $isVisible) hidden @endif>
                @if($isVisible)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $displayLabel }}
                            @if($field->required)<span class="text-red-600" aria-hidden="true">*</span>@endif
                        </label>
                        @if(in_array($field->type, ['radio'], true))
                            @php $radioOptions = match ($field->key ?? '') { 'audience' => $audienceOptions ?? [], 'genre' => $genreOptions ?? [], default => $optionsByFieldId[$field->id] ?? [] }; @endphp
                            <x-requests::radio-group :name="$field->key" :wire-model="'custom.' . $field->key" :options="$radioOptions" :selected="$custom[$field->key] ?? null" variant="card" :required="(bool) $field->required" />
                        @elseif(in_array($field->type, ['select'], true))
                            <x-requests::select-field :name="$field->key" :wire-model="'custom.' . $field->key" :options="$optionsByFieldId[$field->id] ?? []" />
                        @elseif($field->type === 'textarea')
                            <textarea wire:model="custom.{{ $field->key }}" rows="4" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"></textarea>
                        @elseif($field->key === 'publish_date')
                            <input type="text" wire:model="custom.{{ $field->key }}" placeholder="e.g. 2024, Spring 2025, or unknown"
                                   class="w-full max-w-xl rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        @elseif($field->type === 'date')
                            <input type="date" wire:model="custom.{{ $field->key }}" class="w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        @elseif($field->type === 'number')
                            <input type="number" step="0.01" wire:model="custom.{{ $field->key }}" class="w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        @elseif($field->type === 'checkbox')
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="custom.{{ $field->key }}" class="rounded text-blue-600 focus:ring-blue-500" /> Yes
                            </label>
                        @else
                            <input type="text" wire:model="custom.{{ $field->key }}" class="w-full max-w-xl rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        @endif
                        @error('custom.' . $field->key)
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                </div>
                @endif
            @endforeach
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                    class="inline-flex items-center justify-center gap-2 min-w-[10rem] px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="submit">Submit ILL Request</span>
                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Please wait…
                </span>
            </button>
        </div>

        @else
        {{-- ── Normal full form ── --}}
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
                <x-requests::radio-group
                    name="ill_material_type"
                    wire-model="material_type_id"
                    :options="$illMaterialTypes->pluck('name', 'id')->all()"
                    :selected="$material_type_id"
                    variant="card"
                    :required="true"
                />
                @error('material_type_id')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            @foreach($orderedFields as $field)
                @php
                    $isVisible = $visibleFields[$field->key] ?? false;
                    $displayLabel = $displayLabels[$field->key] ?? $field->label;
                @endphp
                @if(in_array($field->key, $step1CustomKeys ?? [], true)) @continue @endif
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
                            <x-requests::radio-group
                                :name="$field->key"
                                :wire-model="'custom.' . $field->key"
                                :options="$radioOptions"
                                :selected="$custom[$field->key] ?? null"
                                variant="card"
                                :required="(bool) $field->required"
                            />
                        @elseif(in_array($field->type, ['select'], true))
                            <x-requests::select-field
                                :name="$field->key"
                                :wire-model="'custom.' . $field->key"
                                :options="$optionsByFieldId[$field->id] ?? []"
                            />
                        @elseif($field->type === 'textarea')
                            <textarea wire:model="custom.{{ $field->key }}" rows="4"
                                      class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
                                      placeholder="{{ $field->key === 'other_specify' ? 'Please describe what you need...' : '' }}"></textarea>
                        @elseif($field->key === 'publish_date')
                            <input type="text" wire:model="custom.{{ $field->key }}" placeholder="e.g. 2024, Spring 2025, or unknown"
                                   class="w-full max-w-xl rounded-md border border-gray-300 px-3 py-2 text-sm" />
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
            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                    class="inline-flex items-center justify-center gap-2 min-w-[10rem] px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="submit">Submit Request</span>
                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Please wait…
                </span>
            </button>
        </div>

        @endif
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
        <div x-data="{ detailOpen: false, detailItem: null, detailIndex: 0, openDetail(i) { this.detailItem = {{ Js::from($isbndbResults) }}[i]; this.detailIndex = i; this.detailOpen = true; } }">
            <h2 id="resolution-heading" class="text-2xl font-bold text-gray-900 mb-4">Verify your book details</h2>
            <p class="text-sm text-gray-600 mb-6">We found possible matches. Confirm the correct edition so we can add ISBN and other details to your request.</p>

            <div class="space-y-4">
                @foreach($isbndbResults as $i => $result)
                    <x-requests::isbndb-result-card :result="$result" :index="$i" />
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

            <x-requests::isbndb-detail-modal />
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

