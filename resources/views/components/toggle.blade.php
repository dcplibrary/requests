{{--
    Reusable pill-toggle switch.

    Usage:
        <x-sfp::toggle :on="$field['active']" color="blue" wire:click="toggleActive({{ $index }})" />

    Props:
        on    — bool   — whether the toggle is currently on
        color — string — 'blue' (default) | 'amber'

    Any extra attributes (wire:click, x-on:click, aria-label, etc.) are merged
    directly onto the <button> element.
--}}
@props([
    'on'    => false,
    'color' => 'blue',
])

{{--
    Tailwind scans for complete class strings. We use explicit @if blocks here
    instead of PHP ternary string construction to guarantee the classes are never
    purged from the compiled stylesheet.
--}}
@if($color === 'amber')
    @if($on)
        <button type="button" role="switch" aria-checked="true"
            {{ $attributes->class(['relative inline-flex h-4 w-7 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-amber-500 bg-amber-500']) }}>
            <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform translate-x-3.5"></span>
        </button>
    @else
        <button type="button" role="switch" aria-checked="false"
            {{ $attributes->class(['relative inline-flex h-4 w-7 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-amber-500 bg-gray-300']) }}>
            <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform translate-x-0.5"></span>
        </button>
    @endif
@else
    @if($on)
        <button type="button" role="switch" aria-checked="true"
            {{ $attributes->class(['relative inline-flex h-4 w-7 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500 bg-blue-600']) }}>
            <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform translate-x-3.5"></span>
        </button>
    @else
        <button type="button" role="switch" aria-checked="false"
            {{ $attributes->class(['relative inline-flex h-4 w-7 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500 bg-gray-300']) }}>
            <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform translate-x-0.5"></span>
        </button>
    @endif
@endif
