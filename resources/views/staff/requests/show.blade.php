@extends('sfp::staff._layout')

@section('title', 'Request #' . $sfpRequest->id)

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.requests.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to requests</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Request #{{ $sfpRequest->id }}</h1>
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $sfpRequest->request_kind === 'ill' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
        {{ strtoupper($sfpRequest->request_kind ?? 'sfp') }}
    </span>
    @if($sfpRequest->status)
        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
              style="background-color: {{ $sfpRequest->status->color }}22; color: {{ $sfpRequest->status->color }};">
            {{ $sfpRequest->status->name }}
        </span>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main info --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Material --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                {{ $sfpRequest->request_kind === 'ill' ? 'ILL Summary' : 'Material' }}
            </h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Type</dt>
                    <dd class="font-medium">{{ $sfpRequest->materialType?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Audience</dt>
                    <dd class="font-medium">{{ $sfpRequest->audience?->name ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-500">Title</dt>
                    <dd class="font-medium">{{ $sfpRequest->material?->title ?? $sfpRequest->submitted_title ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-500">Author</dt>
                    <dd class="font-medium">{{ $sfpRequest->material?->author ?? $sfpRequest->submitted_author ?? '—' }}</dd>
                </div>
                @if($sfpRequest->submitted_publish_date)
                <div>
                    <dt class="text-gray-500">Pub. Date (submitted)</dt>
                    <dd class="font-medium">{{ $sfpRequest->submitted_publish_date }}</dd>
                </div>
                @endif
                @if($sfpRequest->material?->publish_date)
                <div>
                    <dt class="text-gray-500">Pub. Date (ISBNdb)</dt>
                    <dd class="font-medium">{{ $sfpRequest->material->publish_date }}</dd>
                </div>
                @endif
                @if($sfpRequest->material?->isbn)
                <div>
                    <dt class="text-gray-500">ISBN</dt>
                    <dd class="font-mono font-medium">{{ $sfpRequest->material->isbn13 ?? $sfpRequest->material->isbn }}</dd>
                </div>
                @endif
                @if($sfpRequest->material?->publisher)
                <div>
                    <dt class="text-gray-500">Publisher</dt>
                    <dd class="font-medium">{{ $sfpRequest->material->publisher }}</dd>
                </div>
                @endif
                @if($sfpRequest->other_material_text)
                <div class="col-span-2">
                    <dt class="text-gray-500">Other / Notes</dt>
                    <dd class="font-medium">{{ $sfpRequest->other_material_text }}</dd>
                </div>
                @endif
            </dl>
        </div>

        @if($sfpRequest->request_kind === 'ill')
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">ILL Details</h2>
            @php
                $vals = $sfpRequest->customFieldValues
                    ->sortBy(fn($v) => $v->field?->sort_order ?? 9999)
                    ->values();
            @endphp
            @if($vals->isEmpty())
                <p class="text-sm text-gray-400">No custom field values recorded.</p>
            @else
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    @foreach($vals as $val)
                        <div class="md:col-span-1">
                            <dt class="text-gray-500">{{ $val->field?->label ?? $val->field?->key ?? 'Field' }}</dt>
                            <dd class="font-medium">
                                {{ $customValueLabelByFieldId[$val->custom_field_id] ?? ($val->value_text ?? $val->value_slug ?? '—') }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
        @endif

        {{-- Catalog / ILL info --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Catalog &amp; ILL</h2>
                <form method="POST" action="{{ route('sfp.staff.requests.catalog-recheck', $sfpRequest) }}">
                    @csrf
                    <button type="submit"
                            class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                            onclick="return confirm('Re-run catalog search for this request?')">
                        ↺ Re-check catalog
                    </button>
                </form>
            </div>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Catalog searched</dt>
                    <dd>{{ $sfpRequest->catalog_searched ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Catalog results</dt>
                    <dd>{{ $sfpRequest->catalog_result_count ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Catalog match accepted</dt>
                    <dd>{{ $sfpRequest->catalog_match_accepted ? 'Yes' : 'No' }}</dd>
                </div>
                @if($sfpRequest->catalog_match_bib_id)
                <div>
                    <dt class="text-gray-500">Bib ID</dt>
                    <dd>
                        <a href="https://dcpl.bibliocommons.com/v2/record/{{ $sfpRequest->catalog_match_bib_id }}"
                           target="_blank"
                           class="font-mono text-blue-600 hover:underline">
                            {{ $sfpRequest->catalog_match_bib_id }}
                        </a>
                    </dd>
                </div>
                @endif
                <div>
                    <dt class="text-gray-500">ISBNdb searched</dt>
                    <dd>{{ $sfpRequest->isbndb_searched ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">ISBNdb results</dt>
                    <dd>{{ $sfpRequest->isbndb_result_count ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">ILL requested</dt>
                    <dd>{{ $sfpRequest->ill_requested ? 'Yes' : 'No' }}</dd>
                </div>
                @if($sfpRequest->is_duplicate)
                <div>
                    <dt class="text-gray-500">Duplicate of</dt>
                    <dd>
                        @if($sfpRequest->duplicateOf)
                            <a href="{{ route('sfp.staff.requests.show', $sfpRequest->duplicateOf) }}" class="text-blue-600 hover:underline">
                                #{{ $sfpRequest->duplicate_of_request_id }}
                            </a>
                        @else
                            #{{ $sfpRequest->duplicate_of_request_id }}
                        @endif
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Status history --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Status History</h2>
            @if($sfpRequest->statusHistory->isEmpty())
                <p class="text-sm text-gray-400">No status history.</p>
            @else
                <ol class="space-y-3">
                    @foreach($sfpRequest->statusHistory as $entry)
                    <li class="flex gap-3 text-sm">
                        <div class="mt-1 w-2 h-2 rounded-full bg-gray-300 flex-shrink-0"></div>
                        <div>
                            <span class="font-medium">{{ $entry->status?->name ?? 'Unknown' }}</span>
                            @if($entry->user)
                                <span class="text-gray-500"> by {{ $entry->user->name ?? $entry->user->email }}</span>
                            @endif
                            <span class="text-gray-400 text-xs ml-2">{{ $entry->created_at->format('M j, Y g:ia') }}</span>
                            @if($entry->note)
                                <div class="text-gray-600 mt-0.5">{{ $entry->note }}</div>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">

        {{-- Assignment --}}
        @if($assignmentEnabled ?? false)
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Assignment</h2>

            @if($sfpRequest->assignedTo)
                <div class="mb-3">
                    <p class="text-sm font-medium text-gray-900">
                        {{ $sfpRequest->assignedTo->name ?: $sfpRequest->assignedTo->email }}
                    </p>
                    <p class="text-xs text-gray-400">{{ $sfpRequest->assignedTo->email }}</p>
                    @if($sfpRequest->assigned_at)
                        <p class="text-xs text-gray-400 mt-1">Assigned {{ $sfpRequest->assigned_at->format('M j, Y g:ia') }}</p>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-400 mb-3">Unassigned</p>
            @endif

            @if(! $sfpRequest->assigned_to_user_id)
                <form method="POST" action="{{ route('sfp.staff.requests.claim', $sfpRequest) }}" class="mb-3">
                    @csrf
                    <button type="submit"
                            class="w-full px-4 py-2 bg-emerald-600 text-white text-sm rounded hover:bg-emerald-700">
                        Claim
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('sfp.staff.requests.assign', $sfpRequest) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Assign to</label>
                    <select name="assigned_to_user_id" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                        <option value="">Unassigned</option>
                        @foreach($staffUsers as $u)
                            <option value="{{ $u->id }}" @selected($sfpRequest->assigned_to_user_id == $u->id)>
                                {{ $u->name ?: $u->email }} ({{ $u->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                    Save Assignment
                </button>
            </form>
        </div>
        @endif

        {{-- Convert workflow --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Workflow</h2>
            <form method="POST" action="{{ route('sfp.staff.requests.convert-kind', $sfpRequest) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Convert to</label>
                    <select name="to" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                        <option value="sfp" @selected($sfpRequest->request_kind === 'sfp')>SFP</option>
                        <option value="ill" @selected($sfpRequest->request_kind === 'ill')>ILL</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Note (optional)</label>
                    <textarea name="note" rows="2" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
                </div>
                <button type="submit"
                        class="w-full px-4 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700"
                        onclick="return confirm('Convert this request workflow?')">
                    Convert
                </button>
            </form>
        </div>

        {{-- Update status --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Update Status</h2>
            <form method="POST" action="{{ route('sfp.staff.requests.status', $sfpRequest) }}">
                @csrf
                @method('PATCH')
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">New status</label>
                    <select name="status_id" required class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                        <option value="">Select…</option>
                        @foreach($statuses as $s)
                            <option value="{{ $s->id }}" {{ $sfpRequest->request_status_id == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Note (optional)</label>
                    <textarea name="note" rows="3" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
                </div>
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                    Update Status
                </button>
            </form>
        </div>

        {{-- Patron --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Patron</h2>
            @if($sfpRequest->patron)
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-gray-500 text-xs">Name</dt>
                        <dd class="font-medium">{{ $sfpRequest->patron->name_last }}, {{ $sfpRequest->patron->name_first }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Barcode</dt>
                        <dd class="font-mono">{{ $sfpRequest->patron->barcode }}</dd>
                    </div>
                    @if($sfpRequest->patron->email)
                    <div>
                        <dt class="text-gray-500 text-xs">Email</dt>
                        <dd>{{ $sfpRequest->patron->email }}</dd>
                    </div>
                    @endif
                    @if($sfpRequest->patron->phone)
                    <div>
                        <dt class="text-gray-500 text-xs">Phone</dt>
                        <dd>{{ $sfpRequest->patron->phone }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 text-xs">Found in Polaris</dt>
                        <dd>{{ $sfpRequest->patron->found_in_polaris ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-400">No patron data.</p>
            @endif
        </div>

        {{-- Meta --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Meta</h2>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-gray-500 text-xs">Submitted</dt>
                    <dd>{{ $sfpRequest->created_at->format('M j, Y g:ia') }}</dd>
                </div>
                @if($sfpRequest->where_heard)
                <div>
                    <dt class="text-gray-500 text-xs">Where heard</dt>
                    <dd>{{ $sfpRequest->where_heard }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Danger zone --}}
        <div class="bg-white rounded-lg border border-red-200 p-5">
            <h2 class="text-sm font-semibold text-red-700 uppercase tracking-wide mb-3">Danger Zone</h2>
            <form method="POST" action="{{ route('sfp.staff.requests.destroy', $sfpRequest) }}">
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="w-full px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                    onclick="return confirm('Delete Request #{{ $sfpRequest->id }}? This cannot be undone.')"
                >
                    Delete Request
                </button>
            </form>
            <p class="mt-2 text-xs text-gray-500">
                Deleting a request removes it and its status history. The patron and title records are not deleted.
            </p>
        </div>

    </div>
</div>
@endsection
