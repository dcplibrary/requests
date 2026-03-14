{{--
    Circle avatar with computed initials and gradient background.

    Usage:
        <x-requests::avatar name="Brian Lashbrook" />
        <x-requests::avatar name="Jane Doe" size="lg" />

    Props:
        name — string      — full name; initials are derived automatically
        size — string      — 'sm' (w-8 h-8) | 'md' (w-9 h-9, default) | 'lg' (w-12 h-12)
--}}
@props([
    'name' => '',
    'size' => 'md',
])

@php
    $parts    = explode(' ', trim($name));
    $initials = strtoupper(substr($parts[0] ?? '', 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }

    $sizeClasses = match ($size) {
        'sm'    => 'w-8 h-8 text-xs',
        'lg'    => 'w-12 h-12 text-base',
        default => 'w-9 h-9 text-sm',
    };
@endphp

<div {{ $attributes->class([
    'flex items-center justify-center rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 text-white font-semibold',
    $sizeClasses,
]) }}
     title="{{ $name }}">
    {{ $initials ?: 'U' }}
</div>
