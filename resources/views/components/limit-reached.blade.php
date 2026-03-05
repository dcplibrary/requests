@props(['count', 'until' => null])

@php
    $type   = \Dcplibrary\Sfp\Models\Setting::get('sfp_limit_window_type', 'rolling');
    $period = match ($type) {
        'calendar_month' => 'per calendar month',
        'calendar_week'  => 'per week',
        default          => 'every ' . (int) \Dcplibrary\Sfp\Models\Setting::get('sfp_limit_window_days', 30) . ' days',
    };
@endphp

<div class="p-4 bg-amber-50 border border-amber-200 rounded-md" role="alert">
    <p class="text-sm font-medium text-amber-800">
        You have reached the limit of {{ $count }} suggestion{{ $count == 1 ? '' : 's' }} {{ $period }}.
    </p>
    @if($until)
    <p class="mt-1 text-sm text-amber-700">
        You won't be able to submit another suggestion until <strong>{{ $until->format('F j, Y') }}</strong>.
    </p>
    @endif
</div>
