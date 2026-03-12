{{--
    Reusable Alpine.js modal component.

    Usage:
        <x-requests::action-modal name="reassign" title="Reassign Request">
            <p>Modal body content here.</p>
            <x-slot:footer>
                <button @click="$dispatch('close-modal', 'reassign')">Cancel</button>
                <button type="submit">Save</button>
            </x-slot:footer>
        </x-requests::action-modal>

    Open with:  $dispatch('open-modal', 'reassign')
    Close with: $dispatch('close-modal', 'reassign')

    Props:
        name     — string — unique modal identifier
        title    — string — header text
        maxWidth — string — Tailwind max-w class suffix: 'sm', 'md' (default), 'lg', 'xl', '2xl'
--}}
@props([
    'name'     => '',
    'title'    => '',
    'maxWidth' => 'md',
])

@php
    $widthClass = match ($maxWidth) {
        'sm'  => 'max-w-sm',
        'lg'  => 'max-w-lg',
        'xl'  => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-md',
    };
@endphp

<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display: none;">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/60" @click="open = false"></div>

    {{-- Panel --}}
    <div class="relative w-full {{ $widthClass }} flex flex-col bg-white rounded-xl shadow-xl overflow-hidden"
         style="max-height: 90vh;"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 flex-shrink-0">
            <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="overflow-y-auto flex-1 px-5 py-4">
            {{ $slot }}
        </div>

        {{-- Footer (optional slot) --}}
        @if(isset($footer))
        <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            {{ $footer }}
        </div>
        @endif
    </div>
</div>
