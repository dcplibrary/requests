{{--
    Reusable uppercase section heading.

    Usage:
        <x-requests::section-heading>Material</x-requests::section-heading>
        <x-requests::section-heading class="mb-4">With extra margin</x-requests::section-heading>

    Renders an <h2> with the standard section-heading style used throughout
    the staff interface.
--}}
@props([])

<h2 {{ $attributes->class(['text-sm font-medium text-gray-700 uppercase tracking-wide mb-3']) }}>
    {{ $slot }}
</h2>
