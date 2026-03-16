@extends('requests::staff._layout')

@section('title', ($currentKind === 'ill' ? 'ILL Requests' : ($currentKind === 'sfp' ? 'SFP Requests' : 'Requests')))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">
        @if($currentKind === 'ill')
            Interlibrary Loan Requests
        @elseif($currentKind === 'sfp')
            Purchase Requests
        @else
            Suggestions for Purchase
        @endif
    </h1>
    <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">{{ $requests->total() }} total</span>
        @if($currentKind === 'ill' && ($hasIllAccess ?? false))
            <a href="{{ route('request.ill.form') }}" target="_blank"
               class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New ILL Request</a>
        @elseif($currentKind === 'sfp')
            <a href="{{ route('request.form') }}" target="_blank"
               class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New SFP Request</a>
        @else
            <a href="{{ route('request.form') }}" target="_blank"
               class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New SFP Request</a>
            @if($hasIllAccess ?? false)
            <a href="{{ route('request.ill.form') }}" target="_blank"
               class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New ILL Request</a>
            @endif
        @endif
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
    @if($currentKind)
        <input type="hidden" name="kind" value="{{ $currentKind }}">
    @endif

    @if($assignmentEnabled ?? false)
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Assigned</label>
        <select name="assigned" class="text-sm border border-gray-300 rounded px-2 py-1.5">
            <option value="">Any</option>
            <option value="me" {{ ($filters['assigned'] ?? '') === 'me' ? 'selected' : '' }}>Me</option>
            <option value="unassigned" {{ ($filters['assigned'] ?? '') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
        </select>
    </div>
    @endif

    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
        <select name="status" class="text-sm border border-gray-300 rounded px-2 py-1.5">
            <option value="">All statuses</option>
            @foreach($statuses as $s)
                <option value="{{ $s->slug }}" {{ ($filters['status'] ?? '') === $s->slug ? 'selected' : '' }}>
                    {{ $s->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
               placeholder="Title, author, barcode…"
               class="text-sm border border-gray-300 rounded px-2 py-1.5 w-48">
    </div>

    <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Filter</button>
    @if(array_filter($filters))
        @php
            $clearParams = $currentKind ? ['kind' => $currentKind] : [];
        @endphp
        <a href="{{ route('request.staff.requests.index', $clearParams) }}" class="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Clear</a>
    @endif
</form>

{{-- Bulk action bar (visible when checkboxes selected) --}}
<div id="bulk-bar" class="hidden mb-3 flex items-center gap-3 flex-wrap">
    <span class="text-sm text-gray-600"><strong id="bulk-count">0</strong> selected</span>

    <div class="flex items-center gap-1">
        @foreach($statuses as $s)
            <x-requests::status-btn :status="$s" :active="true" class="bulk-status-btn" data-status-id="{{ $s->id }}" />
        @endforeach
    </div>

    @if(($currentStaffUser ?? null)?->isAdmin())
    <button type="button" id="bulk-delete-btn"
            class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-red-300 rounded text-sm text-red-600 hover:bg-red-50 shadow-sm">
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
        </svg>
        Delete
    </button>
    @endif

    @if($assignmentEnabled ?? false)
    <div class="relative">
        <button type="button" id="reassign-btn"
                class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 shadow-sm">
            Reassign
            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
        </button>

        <div id="reassign-dropdown" class="hidden absolute left-0 top-full mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-80 overflow-y-auto">
            @if($selectorGroups->isNotEmpty())
            <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Groups</div>
            @foreach($selectorGroups as $group)
                <button type="button" class="reassign-option w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" data-group-id="{{ $group->id }}">
                    {{ $group->name }}
                </button>
            @endforeach
            @endif

            @if($staffUsers->isNotEmpty())
            <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 {{ $selectorGroups->isNotEmpty() ? 'border-t' : '' }}">Users</div>
            @foreach($staffUsers as $su)
                <button type="button" class="reassign-option w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" data-user-id="{{ $su->id }}">
                    {{ $su->name ?: $su->email }}
                </button>
            @endforeach
            @endif
        </div>
    </div>
    @endif
</div>

@if($assignmentEnabled ?? false)
<form id="bulk-reassign-form" method="POST" action="{{ route('request.staff.requests.bulk-reassign') }}" class="hidden">
    @csrf
    <input type="hidden" name="group_id" id="bulk-group-id">
    <input type="hidden" name="user_id" id="bulk-user-id">
</form>
@endif

<form id="bulk-status-form" method="POST" action="{{ route('request.staff.requests.bulk-status') }}" class="hidden">
    @csrf
    <input type="hidden" name="status_id" id="bulk-status-id">
</form>

<form id="bulk-delete-form" method="POST" action="{{ route('request.staff.requests.bulk-delete') }}" class="hidden">
    @csrf
    @method('DELETE')
</form>

{{-- Table --}}
<x-requests::card padding="p-0" class="overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 w-8">
                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                </th>
                <x-requests::sortable-th column="created_at" label="Submitted" />
                <x-requests::sortable-th column="submitted_title" label="Title / Author" arrow-side="left" />
                @if($currentKind)
                <th class="px-4 py-3 text-left font-medium text-gray-600">Selection Type</th>
                @else
                <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Audience</th>
                @endif
                <th class="px-4 py-3 text-left font-medium text-gray-600">Patron</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                @if($assignmentEnabled ?? false)
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Assignee</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($requests as $req)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('request.staff.requests.show', $req) }}'">
                <td class="px-4 py-3" onclick="event.stopPropagation()">
                    <input type="checkbox" name="selected[]" value="{{ $req->id }}" class="request-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                    {{ $req->created_at->format('M j, Y') }}
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 truncate max-w-xs">
                        {{ $req->material?->title ?? $req->submitted_title ?? '—' }}
                    </div>
                    @if($req->material?->author ?? $req->submitted_author)
                        <div class="text-gray-500 text-xs">{{ $req->material?->author ?? $req->submitted_author }}</div>
                    @endif
                    @if($req->material?->isbn13 ?? $req->material?->isbn)
                        <div class="text-gray-400 text-xs">{{ $req->material?->isbn13 ?? $req->material?->isbn }}</div>
                    @endif
                    @if($req->is_duplicate)
                        <x-requests::badge variant="yellow">Duplicate</x-requests::badge>
                    @endif
                </td>
                @if($currentKind)
                <td class="px-4 py-3">
                    <div class="text-gray-900">{{ $req->fieldValueLabel('material_type') ?? '—' }}</div>
                    @if($groupNameByRequestId[$req->id] ?? null)
                        <div class="text-xs text-gray-500">{{ $groupNameByRequestId[$req->id] }}</div>
                    @endif
                </td>
                @else
                <td class="px-4 py-3 text-gray-600">{{ $req->fieldValueLabel('material_type') ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $req->fieldValueLabel('audience') ?? '—' }}</td>
                @endif
                <td class="px-4 py-3">
                    @if($req->patron)
                        <div class="text-gray-900">{{ $req->patron->name_last }}, {{ $req->patron->name_first }}</div>
                        <div class="text-xs text-gray-400">{{ $req->patron->barcode }}</div>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($req->status && $req->status->icon)
                        <span title="{{ $req->status->name }}">
                            <x-requests::status-icon :name="$req->status->icon" class="h-5 w-5" style="color: {{ $req->status->color }};" />
                        </span>
                    @elseif($req->status)
                        <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $req->status->color }};" title="{{ $req->status->name }}"></span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                @if($assignmentEnabled ?? false)
                <td class="px-4 py-3">
                    @if($req->assignedTo)
                        @php
                            $initials = collect(explode(' ', $req->assignedTo->name ?: $req->assignedTo->email))
                                ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))
                                ->take(2)
                                ->implode('');
                        @endphp
                        <span title="{{ $req->assignedTo->name ?: $req->assignedTo->email }}"
                              class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold cursor-default">
                            {{ $initials }}
                        </span>
                    @else
                        <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 text-gray-400 text-xs cursor-default" title="Unassigned">—</span>
                    @endif
                </td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ (($assignmentEnabled ?? false) ? 8 : 7) - ($currentKind ? 1 : 0) }}"
            </tr>
            @endforelse
        </tbody>
    </table>
</x-requests::card>

<div class="mt-4">
    {{ $requests->links() }}
</div>

<script>
(function () {
    const selectAll  = document.getElementById('select-all');
    const bulkBar    = document.getElementById('bulk-bar');
    const bulkCount  = document.getElementById('bulk-count');
    const reassignBtn = document.getElementById('reassign-btn');
    const dropdown   = document.getElementById('reassign-dropdown');
    const form       = document.getElementById('bulk-reassign-form');

    function getChecked() {
        return document.querySelectorAll('.request-checkbox:checked');
    }

    function syncBar() {
        const count = getChecked().length;
        if (bulkBar) {
            bulkBar.classList.toggle('hidden', count === 0);
            if (bulkCount) bulkCount.textContent = count;
        }
    }

    // Select-all toggle
    selectAll?.addEventListener('change', function () {
        document.querySelectorAll('.request-checkbox').forEach(cb => cb.checked = this.checked);
        syncBar();
    });

    // Individual checkbox changes
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('request-checkbox')) syncBar();
    });

    // Toggle dropdown
    reassignBtn?.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown?.classList.toggle('hidden');
    });

    // Close dropdown on outside click
    document.addEventListener('click', function () {
        dropdown?.classList.add('hidden');
    });

    // Helper: inject selected IDs into a form
    function injectIds(targetForm, checked) {
        targetForm.querySelectorAll('input[name="ids[]"]').forEach(i => i.remove());
        checked.forEach(function (cb) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            targetForm.appendChild(input);
        });
    }

    // Bulk status buttons
    const statusForm = document.getElementById('bulk-status-form');
    document.querySelectorAll('.bulk-status-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const checked = getChecked();
            if (checked.length === 0) return;
            const label = this.title || this.textContent.trim();
            if (!confirm('Change ' + checked.length + ' request(s) to "' + label + '"?')) return;
            injectIds(statusForm, checked);
            document.getElementById('bulk-status-id').value = this.dataset.statusId;
            statusForm.submit();
        });
    });

    // Bulk delete
    const deleteBtn  = document.getElementById('bulk-delete-btn');
    const deleteForm = document.getElementById('bulk-delete-form');
    deleteBtn?.addEventListener('click', function () {
        const checked = getChecked();
        if (checked.length === 0) return;
        if (!confirm('Permanently delete ' + checked.length + ' request(s)? This cannot be undone.')) return;
        injectIds(deleteForm, checked);
        deleteForm.submit();
    });

    // Handle reassign option click
    document.querySelectorAll('.reassign-option').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const checked = getChecked();
            if (checked.length === 0) return;

            const groupId = this.dataset.groupId || '';
            const userId  = this.dataset.userId  || '';
            const label   = this.textContent.trim();

            if (!confirm('Reassign ' + checked.length + ' request(s) to "' + label + '"?')) return;

            injectIds(form, checked);
            document.getElementById('bulk-group-id').value = groupId;
            document.getElementById('bulk-user-id').value  = userId;
            form.submit();
        });
    });
})();
</script>
@endsection
