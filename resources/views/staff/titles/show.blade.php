@extends('requests::staff._layout')

@section('title', $material->title)

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.titles.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Titles</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900 truncate max-w-2xl">{{ $material->title }}</h1>
    <span class="text-sm text-gray-400 font-mono">#{{ $material->id }}</span>
</div>

{{-- Duplicate warning --}}
@if($duplicates->isNotEmpty())
<div class="mb-6 bg-white rounded-lg border border-orange-200">
    <div class="px-5 py-3 border-b border-orange-100 bg-orange-50 rounded-t-lg">
        <h2 class="text-sm font-semibold text-orange-800">
            Possible Duplicate Title{{ $duplicates->count() > 1 ? 's' : '' }} Detected
        </h2>
    </div>
    <div class="p-5 space-y-3">
        @foreach($duplicates as $dup)
        <div class="flex items-center justify-between gap-4 text-sm">
            <div>
                <a href="{{ route('request.staff.titles.show', $dup) }}"
                   class="font-medium text-blue-700 hover:underline">{{ $dup->title }}</a>
                <span class="text-gray-400 mx-1">by</span>
                <span class="text-gray-600">{{ $dup->author }}</span>
                <span class="ml-2 text-xs text-gray-400">{{ $dup->requests_count }} request{{ $dup->requests_count !== 1 ? 's' : '' }} · #{{ $dup->id }}</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs text-gray-500">Merge duplicate into this record:</span>
                <form method="POST" action="{{ route('request.staff.titles.merge', $dup) }}"
                      onsubmit="return confirm('Merge &quot;{{ addslashes($dup->title) }}&quot; into &quot;{{ addslashes($material->title) }}&quot;? This will move all its requests here and delete the duplicate.')">
                    @csrf
                    <input type="hidden" name="target_id" value="{{ $material->id }}">
                    <button type="submit"
                            class="px-3 py-1 text-xs rounded border bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100">
                        Merge into this →
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Title details --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Title Details</h2>
                <div class="flex items-center gap-2">
                    @if($material->source === 'isbndb')
                        <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-700">ISBNdb</span>
                    @elseif($material->source === 'polaris')
                        <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Polaris</span>
                    @else
                        <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Submitted</span>
                    @endif
                </div>
            </div>
            <x-requests::material-details :material="$material" />
        </div>

        {{-- Requests --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                Requests ({{ $material->requests->count() }})
            </h2>
            @if($material->requests->isEmpty())
                <p class="text-sm text-gray-400">No requests linked to this title.</p>
            @else
            <table class="w-full text-sm divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">#</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Patron</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Submitted As</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Audience</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($material->requests->sortByDesc('created_at') as $req)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-gray-400 text-xs">{{ $req->id }}</td>
                        <td class="px-3 py-2">
                            @if($req->patron)
                                <a href="{{ route('request.staff.patrons.show', $req->patron) }}"
                                   class="text-blue-600 hover:underline text-xs">
                                    {{ $req->patron->full_name }}
                                </a>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600 text-xs max-w-[12rem] truncate"
                            title="{{ $req->submitted_title }} / {{ $req->submitted_author }}">
                            {{ $req->submitted_title }}
                            @if($req->submitted_author && strtolower($req->submitted_author) !== strtolower($material->author))
                                <span class="text-gray-400">/ {{ $req->submitted_author }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-500 text-xs">{{ $req->fieldValueLabel('audience') ?? '—' }}</td>
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
                        <td class="px-3 py-2 text-gray-400 text-xs whitespace-nowrap">
                            {{ $req->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <x-requests::icon-btn :href="route('request.staff.requests.show', $req)" variant="view" label="View" />
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">

        {{-- Bulk status update --}}
        @if($material->requests->isNotEmpty())
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                Bulk Status Update
            </h2>
            <p class="text-xs text-gray-500 mb-3">
                Apply a status to all {{ $material->requests->count() }} request{{ $material->requests->count() !== 1 ? 's' : '' }} for this title at once.
            </p>
            <form method="POST" action="{{ route('request.staff.titles.bulk-status', $material) }}">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">New Status</label>
                    <select name="status_id" required
                            class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                        <option value="">Choose status…</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Note <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea name="note" rows="2"
                              placeholder="Reason for status change…"
                              class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
                </div>
                <button type="submit"
                        onclick="return confirm('Update status for all {{ $material->requests->count() }} request(s)?')"
                        class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                    Apply to All Requests
                </button>
            </form>
        </div>
        @endif

        {{-- Meta --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Meta</h2>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Material ID</dt>
                    <dd class="font-mono text-gray-700">#{{ $material->id }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">First added</dt>
                    <dd class="text-gray-700">{{ $material->created_at->format('M j, Y g:ia') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Last updated</dt>
                    <dd class="text-gray-700">{{ $material->updated_at->format('M j, Y g:ia') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Manual merge --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Manual Merge</h2>
            <p class="text-xs text-gray-500 mb-3">
                Move all requests from another title record into this one, then delete that record.
            </p>
            <form method="POST" action=""
                  id="manual-merge-form"
                  onsubmit="return confirmManualMerge(this)">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Title ID to merge in</label>
                    <input type="number" id="manual-merge-id" min="1"
                           placeholder="Material ID…"
                           class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 font-mono">
                </div>
                <input type="hidden" name="target_id" value="{{ $material->id }}">
                <button type="submit"
                        class="w-full px-4 py-2 bg-red-50 text-red-700 border border-red-200 text-sm rounded hover:bg-red-100">
                    Merge into this →
                </button>
            </form>
        </div>

    </div>
</div>

<script>
function confirmManualMerge(form) {
    var loserId = document.getElementById('manual-merge-id').value.trim();
    if (!loserId) { alert('Enter a Material ID to merge.'); return false; }
    var baseUrl = window.location.pathname.replace(/\/titles\/.*$/, '/titles/');
    form.action = baseUrl + loserId + '/merge';
    return confirm('Merge material #' + loserId + ' into this record? All its requests will be moved here and the duplicate will be deleted.');
}
</script>
@endsection
