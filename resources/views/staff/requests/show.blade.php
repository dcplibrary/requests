@extends('sfp::staff._layout')

@section('title', 'Request #' . $sfpRequest->id)

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.requests.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to requests</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Request #{{ $sfpRequest->id }}</h1>
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
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Material</h2>
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

        {{-- Catalog / ILL info --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Catalog &amp; ILL</h2>
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
                    <dd class="font-mono">{{ $sfpRequest->catalog_match_bib_id }}</dd>
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

    </div>
</div>
@endsection
