{{--
    Alpine-powered collapsible section with chevron toggle.

    Usage:
        <x-requests::collapsible title="Activity History" icon="clock">
            <p>Collapsed content here.</p>
        </x-requests::collapsible>

        <x-requests::collapsible title="Details" :open="true">
            <p>Open by default.</p>
        </x-requests::collapsible>

    Props:
        title      — string — heading text
        icon       — string|null — heroicon outline name shown before title (clock, user, etc.)
        open       — bool   — initial open state (default: false)
        showLabel  — string — label for the show button when collapsed (default: 'Show')
        hideLabel  — string — label for the hide button when expanded (default: 'Hide')
--}}
@props([
    'title'     => '',
    'icon'      => null,
    'open'      => false,
    'showLabel' => 'Show',
    'hideLabel' => 'Hide',
])

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-start gap-2">
            @if($icon === 'clock')
                <svg class="h-5 w-5 text-gray-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            @elseif($icon === 'user')
                <svg class="h-5 w-5 text-gray-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
            @endif
            <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
        </div>
        <button type="button"
                @click="open = !open"
                class="inline-flex items-center gap-1 px-2 py-1 text-sm text-gray-500 hover:text-gray-700 rounded hover:bg-gray-100 transition-colors">
            <template x-if="open">
                <span class="flex items-center gap-1">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                    {{ $hideLabel }}
                </span>
            </template>
            <template x-if="!open">
                <span class="flex items-center gap-1">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    {{ $showLabel }}
                </span>
            </template>
        </button>
    </div>

    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak>
        {{ $slot }}
    </div>
</div>
