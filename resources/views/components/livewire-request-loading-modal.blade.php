{{--
    Full-screen modal shown immediately when Livewire begins a request (before server responds).
    Use on patron forms where catalog/ISBNdb work runs in one round-trip.

    @props([
        'targets' => 'submit', // comma-separated Livewire method names, e.g. "submit,skipCatalogMatch"
        'title' => 'Working on your request…',
        'message' => 'We may search our catalog and other sources. That can take 30 seconds or more — please keep this page open.',
    ])

    <x-requests::livewire-request-loading-modal targets="submit" />
    <x-requests::livewire-request-loading-modal
        targets="submit,skipCatalogMatch,skipIsbndbMatch,acceptIsbndbMatch,proceedAsSfp"
        title="Working on your suggestion…"
    />
--}}
@props([
    'targets' => 'submit',
    'title' => 'Working on your request…',
    'message' => 'We may search our catalog and other sources. That can take 30 seconds or more — please keep this page open.',
])

<div wire:loading.flex
     wire:target="{{ $targets }}"
     {{ $attributes->class('fixed inset-0 z-[60] items-center justify-center bg-white/85 backdrop-blur-sm') }}
     role="status"
     aria-live="polite"
     aria-busy="true">
    <div class="bg-white rounded-xl border border-gray-200 shadow-xl px-8 py-6 max-w-md mx-4 text-center">
        <svg class="animate-spin h-9 w-9 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
        <p class="text-base font-semibold text-gray-900">{{ $title }}</p>
        <p class="text-sm text-gray-600 mt-2 leading-relaxed">{{ $message }}</p>
    </div>
</div>
