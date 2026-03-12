{{--
    Inline patron info block for embedding in detail boxes.

    Usage:
        <x-requests::patron-info :patron="$patron" :leap-url="$polarisLeapUrl" />

    Props:
        patron  — Patron model instance
        leapUrl — string|null — Polaris Leap URL template (with {PatronID} placeholder)
--}}
@props([
    'patron'  => null,
    'leapUrl' => null,
])

@if($patron)
<div class="mt-4 pt-4 border-t border-gray-100">
    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Patron</h3>
    <div class="flex items-start justify-between gap-4">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm flex-1">
            <div>
                <dt class="text-gray-500 text-xs">Name</dt>
                <dd class="font-medium">
                    <a href="{{ route('request.staff.patrons.show', $patron) }}"
                       class="text-blue-600 hover:underline">
                        {{ $patron->name_last }}, {{ $patron->name_first }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 text-xs">Barcode</dt>
                <dd class="font-mono text-xs">{{ $patron->barcode }}</dd>
            </div>
            @if($patron->effective_email)
            <div>
                <dt class="text-gray-500 text-xs">Email</dt>
                <dd class="text-xs">{{ $patron->effective_email }}</dd>
            </div>
            @endif
            @if($patron->effective_phone)
            <div>
                <dt class="text-gray-500 text-xs">Phone</dt>
                <dd class="text-xs">{{ $patron->effective_phone }}</dd>
            </div>
            @endif
        </dl>
        @if($leapUrl && $patron->polaris_patron_id)
            @php
                $resolvedLeapUrl = str_replace('{PatronID}', $patron->polaris_patron_id, $leapUrl);
            @endphp
            <x-requests::external-link-btn :href="$resolvedLeapUrl" icon="user" label="View in Polaris Leap" />
        @endif
    </div>
</div>
@endif
