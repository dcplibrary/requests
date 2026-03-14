{{--
    Reusable card container.

    Usage:
        <x-requests::card>Content here</x-requests::card>
        <x-requests::card padding="p-5" :shadow="false">Compact card</x-requests::card>

    Props:
        padding — string — Tailwind padding class (default: 'p-6')
        shadow  — bool   — whether to add shadow-sm (default: true)
--}}
@props([
    'padding' => 'p-6',
    'shadow'  => true,
])

<div {{ $attributes->class([
    'bg-white rounded-lg border border-gray-200',
    $padding,
    'shadow-sm' => $shadow,
]) }}>
    {{ $slot }}
</div>
