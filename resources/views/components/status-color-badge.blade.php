{{--
    Status badge with dynamic color derived from the RequestStatus model.

    Usage:
        <x-requests::status-color-badge :status="$request->status" />

    Props:
        status — RequestStatus|null — the status model; renders '—' when null
--}}
@props([
    'status' => null,
])

@if($status)
    <span {{ $attributes->class(['inline-block px-2 py-0.5 rounded text-xs font-medium']) }}
          style="background-color: {{ $status->color }}22; color: {{ $status->color }};">
        @if($status->icon)
            <x-requests::status-icon :name="$status->icon" class="inline w-3 h-3 -mt-0.5 mr-0.5" />
        @endif
        {{ $status->name }}
    </span>
@else
    <span class="text-gray-400 text-xs">—</span>
@endif
