@props(['count', 'until' => null, 'kind' => 'sfp'])

@php
    $isIll = $kind === 'ill';
    $prefix = $isIll ? 'ill_limit_' : 'sfp_limit_';
    $defaultType = $isIll ? \Dcplibrary\Requests\Models\Patron::LIMIT_TYPE_CONCURRENT : 'rolling';
    $type = \Dcplibrary\Requests\Models\Setting::get($prefix . 'window_type', $defaultType);
    $isConcurrent = $type === \Dcplibrary\Requests\Models\Patron::LIMIT_TYPE_CONCURRENT;

    $period = $isConcurrent ? '' : match ($type) {
        'calendar_month' => 'per calendar month',
        'calendar_week'  => 'per week',
        default          => 'every ' . (int) \Dcplibrary\Requests\Models\Setting::get($prefix . 'window_days', 30) . ' days',
    };

    $messageKey = $isIll ? 'ill_limit_reached_message' : 'limit_reached_message';
    $untilKey   = $isIll ? 'ill_limit_until_message' : 'limit_until_message';
    $defaultMsg = $isConcurrent
        ? ($isIll
            ? 'You can only borrow {limit} items at a time. Please wait until an active request is completed.'
            : 'You have reached the limit of {limit} active suggestions.')
        : ($isIll
            ? 'You have reached the limit of {limit} ILL requests {period}.'
            : 'You have reached the limit of {limit} suggestions {period}.');
    $defaultUntil = $isIll
        ? "You won't be able to submit another ILL request until {until}."
        : "You won't be able to submit another suggestion until {until}.";

    $line1 = trim(preg_replace('/\s+/', ' ', str_replace(
        ['{limit}', '{period}'],
        [$count,    $period],
        \Dcplibrary\Requests\Models\Setting::get($messageKey, $defaultMsg)
    )));

    $line2 = (! $isConcurrent && $until)
        ? str_replace(
            '{until}',
            '<strong>' . $until->format('F j, Y') . '</strong>',
            \Dcplibrary\Requests\Models\Setting::get($untilKey, $defaultUntil)
          )
        : null;
@endphp

<div class="p-4 bg-amber-50 border border-amber-200 rounded-md" role="alert">
    <p class="text-sm font-medium text-amber-800">{{ $line1 }}</p>
    @if($line2)
    <p class="mt-1 text-sm text-amber-700">{!! $line2 !!}</p>
    @endif
    @if($isIll)
    <p class="mt-2 text-sm text-amber-800">
        <a href="{{ route('request.patron.requests') }}" class="font-medium text-amber-900 underline hover:text-amber-950">
            View your existing ILL requests
        </a>
    </p>
    @endif
</div>
