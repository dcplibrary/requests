{{--
    ISBNdb result card — displays a single search result with optional synopsis.

    Usage:
        <x-requests::isbndb-result-card
            :result="$result"
            :index="$i"
            wire-method="acceptIsbndbMatch"
        />

    Props:
        result     — array  — normalized ISBNdb result from IsbnDbService
        index      — int    — position in the results list
        wireMethod — string — Livewire method name for "Yes, this is it" (default: acceptIsbndbMatch)
--}}
@props([
    'result',
    'index',
    'wireMethod' => 'acceptIsbndbMatch',
])

@php
    $synopsis = $result['synopsis'] ?? $result['overview'] ?? null;
    $hasDetails = $synopsis || !empty($result['title_long']) || !empty($result['binding']) || !empty($result['pages']);
@endphp

<div class="p-4 border border-gray-200 rounded-md bg-white shadow-sm">
    <div class="flex items-start gap-3">
        {{-- Cover --}}
        @if(!empty($result['cover_url']))
            <img src="{{ $result['cover_url'] }}"
                 alt="Cover of {{ $result['title'] }}"
                 class="w-12 h-auto object-contain rounded shrink-0" />
        @endif

        {{-- Info --}}
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-gray-900 text-sm">{{ $result['title'] }}</p>
            <p class="text-gray-600 text-sm">{{ $result['author_string'] ?? '' }}</p>

            @if(!empty($result['publisher']) || !empty($result['publish_date']))
                <p class="text-gray-400 text-xs mt-1">
                    {{ $result['publisher'] ?? '' }}{{ ($result['publisher'] ?? '') && ($result['publish_date'] ?? '') ? ' · ' : '' }}{{ $result['publish_date'] ?? '' }}
                </p>
            @endif

            @if(!empty($result['isbn13']))
                <p class="text-gray-400 text-xs font-mono">ISBN {{ $result['isbn13'] }}</p>
            @endif

            {{-- Truncated synopsis --}}
            @if($synopsis)
                <p class="text-gray-500 text-xs mt-2 line-clamp-3 leading-relaxed">{{ $synopsis }}</p>
            @endif

            {{-- More details link --}}
            @if($hasDetails)
                <button
                    type="button"
                    @click="openDetail({{ $index }})"
                    class="mt-1.5 text-xs text-blue-600 hover:text-blue-800 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                >More details</button>
            @endif
        </div>

        {{-- Accept button --}}
        <button
            type="button"
            wire:click="{{ $wireMethod }}({{ $index }})"
            class="shrink-0 px-3 py-1 text-xs font-medium bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
        >Yes, this is it</button>
    </div>
</div>
