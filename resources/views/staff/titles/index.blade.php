@extends('requests::staff._layout')

@section('title', 'Titles')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Titles</h1>
    <div class="flex items-center gap-3">
        @if($unmatchedCount > 0)
            <span class="text-xs bg-amber-100 text-amber-700 border border-amber-200 px-2.5 py-1 rounded-full">
                {{ $unmatchedCount }} unmatched request{{ $unmatchedCount !== 1 ? 's' : '' }}
            </span>
        @endif
        <span class="text-sm text-gray-500">{{ $materials->total() }} title{{ $materials->total() !== 1 ? 's' : '' }}</span>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-lg border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
               placeholder="Title, author, ISBN…"
               class="text-sm border border-gray-300 rounded px-2 py-1.5 w-56">
    </div>
    <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Filter</button>
    @if(array_filter($filters))
        <a href="{{ route('request.staff.titles.index') }}"
           class="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Clear</a>
    @endif
</form>

{{-- Table --}}
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <x-requests::sortable-th column="title" label="Title" />
                <x-requests::sortable-th column="author" label="Author" />
                <th class="px-4 py-3 text-left font-medium text-gray-600">ISBN</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kind</th>
                <x-requests::sortable-th column="requests_count" label="Requests" />
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($materials as $material)
            @php
                $isDuplicate = in_array($material->id, $duplicateMaterialIds);
                $statusCounts = $material->requests->groupBy('request_status_id');
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 max-w-xs truncate" title="{{ $material->title }}">
                        {{ $material->title }}
                    </div>
                    @if($material->publish_date)
                        <div class="text-xs text-gray-400">{{ $material->publish_date }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600 max-w-[12rem] truncate" title="{{ $material->author }}">
                    {{ $material->author }}
                </td>
                <td class="px-4 py-3 font-mono text-xs text-gray-500">
                    {{ $material->isbn13 ?: ($material->isbn ?: '—') }}
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">
                    {{ $material->materialTypeOption?->name ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach($material->requests->pluck('request_kind')->unique()->sort() as $kind)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                {{ $kind === 'ill' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                                {{ strtoupper($kind ?? 'sfp') }}
                            </span>
                        @endforeach
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach($material->requests->groupBy('request_status_id') as $statusId => $reqs)
                            @php $status = $reqs->first()->status @endphp
                            @if($status)
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium"
                                      style="background-color:{{ $status->color }}22;color:{{ $status->color }}">
                                    {{ $reqs->count() }}× {{ $status->name }}
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">{{ $reqs->count() }}×</span>
                            @endif
                        @endforeach
                        @if($material->requests->isEmpty())
                            <span class="text-gray-300 text-xs">0</span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-right">
                    <x-requests::icon-btn :href="route('request.staff.titles.show', $material)" variant="view" label="View" />
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-gray-400">No titles found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $materials->links() }}
</div>
@endsection
