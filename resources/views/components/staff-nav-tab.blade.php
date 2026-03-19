{{--
    Staff primary nav tab (desktop). :label prop (no slot) for correct Livewire/Blaze output.
--}}
@props([
    'href',
    'label',
    'active' => false,
])

@php
    $classes = 'inline-flex items-center justify-center whitespace-nowrap text-white text-[15px] font-semibold tracking-wide px-10 py-3 border-b-[5px] transition-all shrink-0 ';
    $classes .= $active
        ? 'border-dcpl-orange bg-black/20'
        : 'border-transparent hover:bg-black/10 hover:border-white/30';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => trim($classes)]) }}>
    {{ $label }}
</a>
