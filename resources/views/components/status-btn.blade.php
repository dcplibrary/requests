{{--
    Reusable status button with WCAG-compliant contrast.

    Renders a solid button when active, outlined when inactive.
    Automatically picks light or dark text based on background luminance.

    Usage:
        <x-requests::status-btn :status="$status" />
        <x-requests::status-btn :status="$status" :active="true" />
        <x-requests::status-btn :status="$status" size="sm" />

    Props:
        status — RequestStatus model (needs ->id, ->name, ->color, ->icon)
        active — bool  — true = solid fill (current status); false = outlined
        size   — string — 'sm' (compact, for bulk bar) or 'lg' (full-width, for show page)
--}}
@props(['status', 'active' => false, 'size' => 'sm'])

@php
    $hex = ltrim($status->color ?? '#9ca3af', '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    [$r, $g, $b] = [hexdec(substr($hex,0,2))/255, hexdec(substr($hex,2,2))/255, hexdec(substr($hex,4,2))/255];
    $lin = fn($c) => $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    $L = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    $textColor = $L > 0.35 ? '#1f2937' : '#ffffff';

    $sizeClasses = $size === 'lg'
        ? 'w-full h-11 px-4 text-sm'
        : 'px-2.5 py-1.5 text-xs';
@endphp

@if($active)
<button {{ $attributes->merge([
    'type' => 'button',
    'class' => "inline-flex items-center gap-2 rounded font-medium shadow-sm transition-all disabled:opacity-60 {$sizeClasses}",
    'style' => "background-color: {$status->color}; color: {$textColor};",
    'title' => $status->name,
]) }}
    onmouseenter="this.style.filter='brightness(0.85)'"
    onmouseleave="this.style.filter=''">
    @if($status->icon)
        <x-requests::status-icon :name="$status->icon" class="h-4 w-4" />
    @endif
    <span>{{ $status->name }}</span>
</button>
@else
<button {{ $attributes->merge([
    'type' => 'button',
    'class' => "inline-flex items-center gap-2 rounded font-medium border transition-all disabled:opacity-60 {$sizeClasses}",
    'style' => "border-color: color-mix(in srgb, {$status->color} 30%, white); color: {$status->color};",
    'title' => $status->name,
]) }}
    onmouseenter="this.style.backgroundColor='color-mix(in srgb, {{ $status->color }} 10%, white)'"
    onmouseleave="this.style.backgroundColor='transparent'">
    @if($status->icon)
        <x-requests::status-icon :name="$status->icon" class="h-4 w-4" />
    @endif
    <span>{{ $status->name }}</span>
</button>
@endif
