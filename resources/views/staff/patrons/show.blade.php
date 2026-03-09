@extends('sfp::staff._layout')

@section('title', 'Patron: ' . $patron->full_name)

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.patrons.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Patrons</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $patron->full_name }}</h1>
    <span class="text-sm text-gray-400 font-mono">#{{ $patron->id }}</span>
</div>

{{-- Possible Duplicates Section --}}
@if($suspects->isNotEmpty())
<div class="mb-6 bg-white rounded-lg border border-orange-200" id="duplicates-section">
    <div class="px-5 py-3 border-b border-orange-100 bg-orange-50 rounded-t-lg flex items-center justify-between">
        <h2 class="text-sm font-semibold text-orange-800">
            Possible Duplicate{{ $suspects->count() > 1 ? 's' : '' }} Detected
        </h2>
        <span class="text-xs text-orange-600">Select Primary and Merge targets, then click Apply</span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm" id="duplicates-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">First Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Last Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Barcode</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Phone</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Polaris</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="duplicates-tbody">

                {{-- Current patron row (always shown, represents "this" patron) --}}
                @php
                    $currentIsPrimary = $patron->found_in_polaris;
                @endphp
                <tr class="duplicate-row bg-blue-50/40" data-patron-id="{{ $patron->id }}"
                    data-name-first="{{ $patron->name_first }}"
                    data-name-last="{{ $patron->name_last }}"
                    data-barcode="{{ $patron->barcode }}"
                    data-phone="{{ $patron->phone }}"
                    data-email="{{ $patron->email }}"
                    data-polaris-id="{{ $patron->polaris_patron_id }}"
                    data-polaris-phone="{{ $patron->polaris_phone }}"
                    data-polaris-email="{{ $patron->polaris_email }}"
                    data-found-in-polaris="{{ $patron->found_in_polaris ? '1' : '0' }}">
                    <td class="px-4 py-3 text-gray-900">
                        {{ $patron->name_first }}
                        <span class="ml-1 text-xs text-blue-500 font-medium">(this record)</span>
                    </td>
                    <td class="px-4 py-3 text-gray-900">{{ $patron->name_last }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $patron->barcode }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $patron->phone ?: '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $patron->email ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @if($patron->found_in_polaris)
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Found</span>
                        @elseif($patron->polaris_lookup_attempted)
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-600">Not found</span>
                        @else
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Pending</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="setPrimary(this)"
                                    class="primary-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border
                                           {{ $currentIsPrimary ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400' }}"
                                    title="Set as primary (keep this record)">
                                ★ Primary
                            </button>
                            <button type="button"
                                    onclick="setMerge(this)"
                                    class="merge-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border
                                           {{ $currentIsPrimary ? 'bg-white text-gray-600 border-gray-300 hover:border-green-400' : 'bg-green-600 text-white border-green-600' }}"
                                    title="Merge this record into primary">
                                Merge
                            </button>
                        </div>
                    </td>
                </tr>

                {{-- Suspect rows --}}
                @foreach($suspects as $suspect)
                @php
                    $suspectIsPrimary = $suspect->found_in_polaris && ! $currentIsPrimary;
                @endphp
                <tr class="duplicate-row" data-patron-id="{{ $suspect->id }}"
                    data-name-first="{{ $suspect->name_first }}"
                    data-name-last="{{ $suspect->name_last }}"
                    data-barcode="{{ $suspect->barcode }}"
                    data-phone="{{ $suspect->phone }}"
                    data-email="{{ $suspect->email }}"
                    data-polaris-id="{{ $suspect->polaris_patron_id }}"
                    data-polaris-phone="{{ $suspect->polaris_phone }}"
                    data-polaris-email="{{ $suspect->polaris_email }}"
                    data-found-in-polaris="{{ $suspect->found_in_polaris ? '1' : '0' }}">
                    <td class="px-4 py-3 text-gray-900">{{ $suspect->name_first }}</td>
                    <td class="px-4 py-3 text-gray-900">{{ $suspect->name_last }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $suspect->barcode }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $suspect->phone ?: '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $suspect->email ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @if($suspect->found_in_polaris)
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Found</span>
                        @elseif($suspect->polaris_lookup_attempted)
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-600">Not found</span>
                        @else
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Pending</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="setPrimary(this)"
                                    class="primary-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border
                                           {{ $suspectIsPrimary ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400' }}"
                                    title="Set as primary (keep this record)">
                                ★ Primary
                            </button>
                            <button type="button"
                                    onclick="setMerge(this)"
                                    class="merge-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border
                                           {{ $suspectIsPrimary ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:border-green-400' }}"
                                    title="Merge this record into primary">
                                Merge
                            </button>
                            <form method="POST"
                                  action="{{ route('request.staff.patrons.ignore-duplicate', $patron) }}"
                                  class="inline ignore-form">
                                @csrf
                                <input type="hidden" name="other_id" value="{{ $suspect->id }}">
                                <button type="submit"
                                        onclick="return confirmIgnore(this)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border bg-white text-gray-400 border-gray-200 hover:text-red-500 hover:border-red-300"
                                        title="Mark as not a duplicate">
                                    ✕ Not duplicate
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach

            </tbody>
        </table>
    </div>

    <div class="px-5 py-3 border-t border-gray-100 flex items-center gap-3">
        <button type="button" onclick="applyDuplicates()"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            Apply
        </button>
        <span class="text-xs text-gray-400">Select one Primary and at least one Merge, then click Apply</span>
    </div>
</div>
@endif

{{-- Merge Modal --}}
<div id="merge-modal" class="fixed inset-0 z-50 hidden" aria-modal="true" role="dialog">
    <div class="fixed inset-0 bg-black/40" onclick="closeMergeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Confirm Merge</h2>
                <button onclick="closeMergeModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div class="px-6 py-5">
                <p class="text-sm text-gray-600 mb-5">
                    Choose which values to keep for the merged record. The <strong>Polaris</strong> column shows data
                    from the library system; <strong>Submitted</strong> shows what the patron entered on the form.
                    Polaris values are selected by default when available.
                </p>

                {{-- Polaris Patron ID --}}
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Polaris Patron ID</label>
                    <div id="modal-polaris-id-display"></div>
                </div>

                {{-- Per-field selection table --}}
                <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden mb-5">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-600 w-24">Field</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-600">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                                    Polaris
                                </span>
                            </th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-600">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>
                                    Submitted
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="px-4 py-3 text-xs font-medium text-gray-500">Phone</td>
                            <td class="px-4 py-3" id="modal-phone-polaris-cell"></td>
                            <td class="px-4 py-3" id="modal-phone-submitted-cell"></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-xs font-medium text-gray-500">Email</td>
                            <td class="px-4 py-3" id="modal-email-polaris-cell"></td>
                            <td class="px-4 py-3" id="modal-email-submitted-cell"></td>
                        </tr>
                    </tbody>
                </table>

                {{-- Losers summary --}}
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Records being merged in (will be deleted)</label>
                    <div id="modal-losers-list" class="space-y-1"></div>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-5">
                    <p class="text-xs text-amber-800">
                        <strong>This action is irreversible.</strong>
                        All requests from merged records will be moved to the primary, and those records will be permanently deleted.
                    </p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button onclick="closeMergeModal()" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
                    Cancel
                </button>
                <button onclick="submitMerge()"
                        class="px-5 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 font-medium">
                    Merge Records
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Hidden merge form (populated by JS before submit) --}}
<form id="merge-form" method="POST" action="" style="display:none">
    @csrf
    <input type="hidden" name="target_id" id="merge-target-id">
    <input type="hidden" name="polaris_patron_id" id="merge-polaris-patron-id">
    <input type="hidden" name="preferred_phone" id="merge-preferred-phone">
    <input type="hidden" name="preferred_email" id="merge-preferred-email">
</form>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column (2/3) --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Submitted data --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Submitted Data</h2>
                <x-sfp::icon-btn :href="route('request.staff.patrons.edit', $patron)" variant="edit" label="Edit" />
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500 mb-0.5">Barcode</dt>
                    <dd class="font-mono font-medium">{{ $patron->barcode }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 mb-0.5">Name</dt>
                    <dd class="font-medium">{{ $patron->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 mb-0.5">Phone</dt>
                    <dd>{{ $patron->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 mb-0.5">Email</dt>
                    <dd>{{ $patron->email ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Polaris lookup & comparison --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Polaris Lookup</h2>
                <form method="POST" action="{{ route('request.staff.patrons.retrigger-polaris', $patron) }}">
                    @csrf
                    <button type="submit"
                            class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                            onclick="return confirm('Re-queue Polaris lookup for this patron?')">
                        ↺ Re-trigger lookup
                    </button>
                </form>
            </div>

            <div class="mb-4">
                @if(! $patron->polaris_lookup_attempted)
                    <span class="inline-block px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Lookup pending</span>
                @elseif($patron->found_in_polaris)
                    <span class="inline-block px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Found in Polaris</span>
                    @if($patron->polaris_lookup_at)
                        <span class="text-xs text-gray-400 ml-2">{{ $patron->polaris_lookup_at->format('M j, Y g:ia') }}</span>
                    @endif
                @else
                    <span class="inline-block px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">Not found in Polaris</span>
                    @if($patron->polaris_lookup_at)
                        <span class="text-xs text-gray-400 ml-2">Last checked {{ $patron->polaris_lookup_at->format('M j, Y g:ia') }}</span>
                    @endif
                @endif
            </div>

            @if($patron->found_in_polaris)
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Field</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Submitted</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Polaris</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Match</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach([
                        ['First Name', $patron->name_first, $patron->polaris_name_first, $patron->name_first_matches],
                        ['Last Name',  $patron->name_last,  $patron->polaris_name_last,  $patron->name_last_matches],
                        ['Phone',      $patron->phone,      $patron->polaris_phone,      $patron->phone_matches],
                        ['Email',      $patron->email,      $patron->polaris_email,      $patron->email_matches],
                    ] as [$label, $submitted, $polaris, $matches])
                    <tr>
                        <td class="px-3 py-2 text-gray-600">{{ $label }}</td>
                        <td class="px-3 py-2 text-gray-900">{{ $submitted ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $polaris ?: '—' }}</td>
                        <td class="px-3 py-2">
                            @if($matches === null)
                                <span class="text-gray-400 text-xs">—</span>
                            @elseif($matches)
                                <span class="text-green-600 text-xs font-medium">✓ Match</span>
                            @else
                                <span class="text-red-600 text-xs font-medium">✗ Mismatch</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($patron->polaris_patron_id)
            <p class="mt-2 text-xs text-gray-400">Polaris Patron ID: {{ $patron->polaris_patron_id }}</p>
            @endif
            @endif
        </div>

        {{-- Request history --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                Requests ({{ $patron->requests->count() }})
            </h2>
            @if($patron->requests->isEmpty())
                <p class="text-sm text-gray-400">No requests.</p>
            @else
            <table class="w-full text-sm divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">#</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Title</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Type</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($patron->requests->sortByDesc('created_at') as $req)
                    <tr>
                        <td class="px-3 py-2 text-gray-400 text-xs">{{ $req->id }}</td>
                        <td class="px-3 py-2 text-gray-900 max-w-xs truncate">
                            {{ $req->submitted_title }}
                            @if($req->is_duplicate)
                                <span class="ml-1 text-xs bg-yellow-100 text-yellow-700 px-1 py-0.5 rounded">Dup</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-500 text-xs">{{ $req->materialType?->name ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @if($req->status)
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium"
                                      style="background-color:{{ $req->status->color }}22;color:{{ $req->status->color }}">
                                    {{ $req->status->name }}
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-400 text-xs whitespace-nowrap">{{ $req->created_at->format('M j, Y') }}</td>
                        <td class="px-3 py-2 text-right">
                            <x-sfp::icon-btn :href="route('request.staff.requests.show', $req)" variant="view" label="View" />
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

    </div>

    {{-- Sidebar (1/3) --}}
    <div class="space-y-6">

        {{-- Meta --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Meta</h2>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">First seen</dt>
                    <dd class="text-gray-700">{{ $patron->created_at->format('M j, Y g:ia') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Last updated</dt>
                    <dd class="text-gray-700">{{ $patron->updated_at->format('M j, Y g:ia') }}</dd>
                </div>
                @if($patron->polaris_patron_id)
                <div>
                    <dt class="text-xs text-gray-500">Polaris Patron ID</dt>
                    <dd class="text-gray-700 font-mono">{{ $patron->polaris_patron_id }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Manual merge fallback --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Manual Merge</h2>
            <p class="text-xs text-gray-500 mb-3">
                Enter another patron's ID to review a merge. This record will be treated as the one to delete.
            </p>
            <form method="GET" action="{{ route('request.staff.patrons.merge-confirm', $patron) }}">
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Winner patron ID</label>
                    <input type="number" name="target_id" required min="1"
                           placeholder="Patron ID to keep…"
                           class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 font-mono">
                </div>
                <button type="submit"
                        class="w-full px-4 py-2 bg-red-50 text-red-700 border border-red-200 text-sm rounded hover:bg-red-100">
                    Review Merge →
                </button>
            </form>
        </div>

    </div>
</div>

@if($suspects->isNotEmpty())
<script>
{{-- Blade emits the merge URL for every patron involved so JS never has to guess paths --}}
var SFP_MERGE_URLS = {
    {{ $patron->id }}: "{{ route('request.staff.patrons.merge', $patron) }}",
    @foreach($suspects as $suspect)
    {{ $suspect->id }}: "{{ route('request.staff.patrons.merge', $suspect) }}",
    @endforeach
};
</script>
<script>
(function () {
    // ── State ────────────────────────────────────────────────────────────────
    // Each row holds: { el, patronId, state: 'primary'|'merge'|'none' }

    // Initialise based on button states already set by Blade
    function initState() {
        document.querySelectorAll('.duplicate-row').forEach(function(row) {
            const primaryBtn = row.querySelector('.primary-btn');
            const mergeBtn   = row.querySelector('.merge-btn');
            if (primaryBtn && primaryBtn.classList.contains('bg-blue-600')) {
                row.dataset.state = 'primary';
            } else if (mergeBtn && mergeBtn.classList.contains('bg-green-600')) {
                row.dataset.state = 'merge';
            } else {
                row.dataset.state = 'none';
            }
        });
    }

    // ── Button handlers ──────────────────────────────────────────────────────

    window.setPrimary = function(btn) {
        const row = btn.closest('.duplicate-row');

        // If already primary, deselect
        if (row.dataset.state === 'primary') {
            setRowState(row, 'none');
            return;
        }

        // Demote any existing primary to 'none'
        document.querySelectorAll('.duplicate-row[data-state="primary"]').forEach(function(r) {
            setRowState(r, 'none');
        });

        setRowState(row, 'primary');
    };

    window.setMerge = function(btn) {
        const row = btn.closest('.duplicate-row');

        // Toggle
        if (row.dataset.state === 'merge') {
            setRowState(row, 'none');
        } else if (row.dataset.state === 'primary') {
            // Clicking Merge on the Primary promotes the previous primary to none and sets this to merge
            setRowState(row, 'merge');
        } else {
            setRowState(row, 'merge');
        }
    };

    window.confirmIgnore = function(btn) {
        return confirm('Mark these two patrons as not duplicates? This will hide them from each other\'s duplicate panel.');
    };

    function setRowState(row, state) {
        row.dataset.state = state;
        const primaryBtn = row.querySelector('.primary-btn');
        const mergeBtn   = row.querySelector('.merge-btn');

        // Reset both buttons to inactive style
        if (primaryBtn) {
            primaryBtn.className = 'primary-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border bg-white text-gray-600 border-gray-300 hover:border-blue-400';
        }
        if (mergeBtn) {
            mergeBtn.className = 'merge-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border bg-white text-gray-600 border-gray-300 hover:border-green-400';
        }

        if (state === 'primary' && primaryBtn) {
            primaryBtn.className = 'primary-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border bg-blue-600 text-white border-blue-600';
        } else if (state === 'merge' && mergeBtn) {
            mergeBtn.className = 'merge-btn inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs border bg-green-600 text-white border-green-600';
        }
    }

    // ── Apply / Validate ─────────────────────────────────────────────────────

    window.applyDuplicates = function() {
        const rows    = Array.from(document.querySelectorAll('.duplicate-row'));
        const primary = rows.find(r => r.dataset.state === 'primary');
        const merges  = rows.filter(r => r.dataset.state === 'merge');

        if (!primary) {
            alert('Please select one record as Primary.');
            return;
        }
        if (merges.length === 0) {
            alert('Please select at least one record to Merge into the primary.');
            return;
        }

        openMergeModal(primary, merges);
    };

    // ── Modal ────────────────────────────────────────────────────────────────

    function openMergeModal(primary, merges) {
        const pd = primary.dataset;

        // ── Polaris Patron ID ────────────────────────────────────────────────
        // Collect all polaris IDs from primary + merges
        const allRows        = [primary].concat(merges);
        const allPolarisIds  = allRows
            .map(r => r.dataset.polarisId)
            .filter(id => id && id.trim() !== '');
        const uniquePolarisIds = [...new Set(allPolarisIds)];

        const polarisIdContainer = document.getElementById('modal-polaris-id-display');
        polarisIdContainer.innerHTML = '';

        if (uniquePolarisIds.length === 1) {
            // One known ID — show as text + hidden input
            polarisIdContainer.innerHTML =
                '<p class="text-sm font-mono text-gray-900">' + esc(uniquePolarisIds[0]) + '</p>' +
                '<input type="hidden" id="modal-polaris-id-value" value="' + esc(uniquePolarisIds[0]) + '">';
        } else if (uniquePolarisIds.length > 1) {
            // Multiple IDs — let staff pick
            const radios = uniquePolarisIds.map(function(id, i) {
                return '<label class="flex items-center gap-2 text-sm cursor-pointer">' +
                    '<input type="radio" name="modal_polaris_id" value="' + esc(id) + '"' +
                    (i === 0 ? ' checked' : '') + ' class="accent-blue-600">' +
                    '<span class="font-mono">' + esc(id) + '</span>' +
                    '</label>';
            }).join('');
            polarisIdContainer.innerHTML =
                '<p class="text-xs text-amber-700 mb-2">Multiple Polaris IDs found — choose one:</p>' +
                '<div class="space-y-1">' + radios + '</div>';
        } else {
            // None — let staff enter one
            polarisIdContainer.innerHTML =
                '<p class="text-xs text-gray-500 mb-2">No Polaris Patron ID on any of these records. Enter one if known:</p>' +
                '<input type="text" id="modal-polaris-id-value" placeholder="Polaris Patron ID (optional)" ' +
                'class="border border-gray-300 rounded px-2 py-1.5 text-sm font-mono w-48">';
        }

        // ── Phone ────────────────────────────────────────────────────────────
        const primaryPolPhone = pd.polarisPhone || '';
        const primarySubPhone = pd.phone        || '';
        buildFieldCell(
            'modal-phone-polaris-cell',
            'modal-phone-submitted-cell',
            'preferred_phone',
            primaryPolPhone,
            primarySubPhone,
            'polaris'
        );

        // ── Email ────────────────────────────────────────────────────────────
        const primaryPolEmail = pd.polarisEmail || '';
        const primarySubEmail = pd.email        || '';
        buildFieldCell(
            'modal-email-polaris-cell',
            'modal-email-submitted-cell',
            'preferred_email',
            primaryPolEmail,
            primarySubEmail,
            'polaris'
        );

        // ── Losers list ──────────────────────────────────────────────────────
        const losersList = document.getElementById('modal-losers-list');
        losersList.innerHTML = merges.map(function(r) {
            return '<div class="flex items-center gap-2 text-sm text-gray-700 bg-gray-50 rounded px-3 py-2">' +
                '<span class="font-medium">' + esc(r.dataset.nameFirst) + ' ' + esc(r.dataset.nameLast) + '</span>' +
                '<span class="text-gray-400 font-mono text-xs">' + esc(r.dataset.barcode) + '</span>' +
                '<span class="text-gray-400 text-xs">ID #' + esc(r.dataset.patronId) + '</span>' +
                '</div>';
        }).join('');

        // Store context for submit
        document.getElementById('merge-modal').dataset.primaryId = pd.patronId;
        document.getElementById('merge-modal').dataset.loserIds  = merges.map(r => r.dataset.patronId).join(',');

        document.getElementById('merge-modal').classList.remove('hidden');
    }

    function buildFieldCell(polarisCellId, submittedCellId, fieldName, polarisVal, submittedVal, defaultChoice) {
        const polarisCell   = document.getElementById(polarisCellId);
        const submittedCell = document.getElementById(submittedCellId);
        const hasBoth = polarisVal && submittedVal;

        if (hasBoth) {
            // Show radio buttons — staff must choose
            polarisCell.innerHTML =
                '<label class="flex items-center gap-2 cursor-pointer text-sm">' +
                '<input type="radio" name="' + fieldName + '" value="polaris"' +
                (defaultChoice === 'polaris' ? ' checked' : '') + ' class="accent-blue-600">' +
                '<span>' + esc(polarisVal) + '</span>' +
                '</label>';
            submittedCell.innerHTML =
                '<label class="flex items-center gap-2 cursor-pointer text-sm">' +
                '<input type="radio" name="' + fieldName + '" value="submitted"' +
                (defaultChoice === 'submitted' ? ' checked' : '') + ' class="accent-blue-600">' +
                '<span>' + esc(submittedVal) + '</span>' +
                '</label>';
        } else if (polarisVal) {
            // Only Polaris value — use hidden input, no choice needed
            polarisCell.innerHTML =
                '<span class="text-sm">' + esc(polarisVal) + '</span>' +
                '<input type="hidden" name="' + fieldName + '" value="polaris">';
            submittedCell.innerHTML = '<span class="text-gray-300 text-xs">—</span>';
        } else if (submittedVal) {
            // Only submitted value — use hidden input, no choice needed
            polarisCell.innerHTML = '<span class="text-gray-300 text-xs">—</span>';
            submittedCell.innerHTML =
                '<span class="text-sm">' + esc(submittedVal) + '</span>' +
                '<input type="hidden" name="' + fieldName + '" value="submitted">';
        } else {
            // Neither has a value
            polarisCell.innerHTML   = '<span class="text-gray-300 text-xs">—</span>';
            submittedCell.innerHTML = '<span class="text-gray-300 text-xs">—</span>';
        }
    }

    window.closeMergeModal = function() {
        document.getElementById('merge-modal').classList.add('hidden');
    };

    window.submitMerge = function() {
        const modal     = document.getElementById('merge-modal');
        const primaryId = modal.dataset.primaryId;
        const loserIds  = modal.dataset.loserIds.split(',').filter(Boolean);
        const loserId   = loserIds[0];

        // Look up the pre-generated merge URL for this loser patron
        const mergeUrl = SFP_MERGE_URLS[loserId];
        if (!mergeUrl) {
            alert('Could not determine merge URL. Please refresh and try again.');
            return;
        }

        // Preferred phone — checked radio if two options, else the hidden input, else default
        const phoneInput = document.querySelector('input[name="preferred_phone"]:checked') ||
                           document.querySelector('input[name="preferred_phone"][type="hidden"]');
        const preferredPhone = phoneInput ? phoneInput.value : 'submitted';

        // Preferred email
        const emailInput = document.querySelector('input[name="preferred_email"]:checked') ||
                           document.querySelector('input[name="preferred_email"][type="hidden"]');
        const preferredEmail = emailInput ? emailInput.value : 'submitted';

        // Polaris patron ID — hidden input (single known ID) or radio (multiple) or text (none known)
        let polarisId = '';
        const polarisIdValueEl = document.getElementById('modal-polaris-id-value');
        if (polarisIdValueEl) {
            polarisId = polarisIdValueEl.value.trim();
        } else {
            const polarisIdRadio = document.querySelector('input[name="modal_polaris_id"]:checked');
            if (polarisIdRadio) polarisId = polarisIdRadio.value.trim();
        }

        const form = document.getElementById('merge-form');
        form.action = mergeUrl;
        document.getElementById('merge-target-id').value         = primaryId;
        document.getElementById('merge-polaris-patron-id').value = polarisId;
        document.getElementById('merge-preferred-phone').value   = preferredPhone;
        document.getElementById('merge-preferred-email').value   = preferredEmail;

        form.submit();
    };

    // ── Escape helper ─────────────────────────────────────────────────────────
    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    initState();

})();
</script>
@endif
@endsection
