@props(['count', 'until' => null])

@php
    $type   = \Dcplibrary\Sfp\Models\Setting::get('sfp_limit_window_type', 'rolling');
    $period = match ($type) {
        'calendar_month' => 'per calendar month',
        'calendar_week'  => 'per week',
        default          => 'every ' . (int) \Dcplibrary\Sfp\Models\Setting::get('sfp_limit_window_days', 30) . ' days',
    };

    $line1 = str_replace(
        ['{limit}', '{period}'],
        [$count,    $period],
        \Dcplibrary\Sfp\Models\Setting::get('limit_reached_message', 'You have reached the limit of {limit} suggestions {period}.')
    );

    $line2 = $until
        ? str_replace(
            '{until}',
            '<strong>' . $until->format('F j, Y') . '</strong>',
            \Dcplibrary\Sfp\Models\Setting::get('limit_until_message', "You won't be able to submit another suggestion until {until}.")
          )
        : null;
@endphp

<div class="p-4 bg-amber-50 border border-amber-200 rounded-md" role="alert">
    <p class="text-sm font-medium text-amber-800">{{ $line1 }}</p>
    @if($line2)
    <p class="mt-1 text-sm text-amber-700">{!! $line2 !!}</p>
    @endif
</div>
