{{--
    Single item within an info-bar: icon + label + value.

    Usage:
        <x-requests::info-bar-item icon="clock" label="Submitted" value="Mar 12, 2026" />
        <x-requests::info-bar-item icon="user"  label="Assigned to" value="Brian Lashbrook" />
        <x-requests::info-bar-item label="Status" value="Pending" dot-color="orange" />

    Props:
        icon     — string|null — heroicon name: 'clock', 'user' (renders outline SVG)
        label    — string      — muted label text
        value    — string      — bold value text
        dotColor — string|null — CSS color for a filled dot (replaces the icon)
--}}
@props([
    'icon'     => null,
    'label'    => '',
    'value'    => '',
    'dotColor' => null,
])

<div class="flex items-center gap-2">
    @if($dotColor)
        <span class="inline-block h-3 w-3 rounded-full flex-shrink-0" style="background-color: {{ $dotColor }};"></span>
    @elseif($icon === 'clock')
        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    @elseif($icon === 'user')
        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
    @endif
    <span class="text-gray-600">{{ $label }}:</span>
    <span class="font-medium text-gray-900">{{ $value }}</span>
</div>
