@extends('sfp::staff._layout')

@section('title', 'Requests')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Purchase Requests</h1>
    <span class="text-sm text-gray-500">{{ $requests->total() }} total</span>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
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
        <label class="block text-xs font-medium text-gray-600 mb-1">Material Type</label>
        <select name="material_type" class="text-sm border border-gray-300 rounded px-2 py-1.5">
            <option value="">All types</option>
            @foreach($materialTypes as $mt)
                <option value="{{ $mt->id }}" {{ ($filters['material_type'] ?? '') == $mt->id ? 'selected' : '' }}>
                    {{ $mt->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Audience</label>
        <select name="audience" class="text-sm border border-gray-300 rounded px-2 py-1.5">
            <option value="">All audiences</option>
            @foreach($audiences as $a)
                <option value="{{ $a->id }}" {{ ($filters['audience'] ?? '') == $a->id ? 'selected' : '' }}>
                    {{ $a->name }}
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
        <a href="{{ route('sfp.staff.requests.index') }}" class="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Clear</a>
    @endif
</form>

{{-- Table --}}
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">#</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Title / Author</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Audience</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Patron</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Submitted</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($requests as $req)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-400">{{ $req->id }}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 truncate max-w-xs">
                        {{ $req->material?->title ?? $req->submitted_title ?? '—' }}
                    </div>
                    @if($req->material?->author ?? $req->submitted_author)
                        <div class="text-gray-500 text-xs">{{ $req->material?->author ?? $req->submitted_author }}</div>
                    @endif
                    @if($req->is_duplicate)
                        <span class="inline-block mt-0.5 text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded">Duplicate</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $req->materialType?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $req->audience?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if($req->patron)
                        <div class="text-gray-900">{{ $req->patron->name_last }}, {{ $req->patron->name_first }}</div>
                        <div class="text-xs text-gray-400">{{ $req->patron->barcode }}</div>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($req->status)
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                              style="background-color: {{ $req->status->color }}22; color: {{ $req->status->color }};">
                            {{ $req->status->name }}
                        </span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                    {{ $req->created_at->format('M j, Y') }}
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('sfp.staff.requests.show', $req) }}"
                       class="text-blue-600 hover:underline text-xs font-medium">View</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-10 text-center text-gray-400">No requests found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $requests->links() }}
</div>
@endsection
