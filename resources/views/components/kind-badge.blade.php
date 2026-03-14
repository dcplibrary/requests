{{--
    Request-kind badge (SFP or ILL).

    Usage:
        <x-requests::kind-badge :kind="$request->request_kind" />

    Props:
        kind — string|null — 'sfp' or 'ill' (defaults to 'sfp')
--}}
@props([
    'kind' => 'sfp',
])

@php
    $isIll = strtolower($kind ?? 'sfp') === 'ill';
@endphp

<span {{ $attributes->class([
    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
    'bg-purple-50 text-purple-700' => $isIll,
    'bg-blue-50 text-blue-700'     => ! $isIll,
]) }}>
    {{ strtoupper($kind ?? 'sfp') }}
</span>
