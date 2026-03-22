@extends('requests::staff._layout')

@section('title', 'Patrons')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Patrons</h1>
    <span class="text-sm text-gray-500">{{ $patrons->total() }} {{ $showAll ? 'total' : 'flagged' }}</span>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-lg border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
               placeholder="Barcode, name, email…"
               class="text-sm border border-gray-300 rounded px-2 py-1.5 w-52">
    </div>
    <div class="flex items-center gap-2 self-end pb-1.5">
        <input type="hidden" name="show_all" value="0">
        <input type="checkbox" name="show_all" id="show_all" value="1"
               {{ $showAll ? 'checked' : '' }}
               class="w-4 h-4 rounded border-gray-300 text-blue-600">
        <label for="show_all" class="text-sm text-gray-700">Show all patrons</label>
    </div>
    <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Filter</button>
    @if(array_filter($filters))
        <a href="{{ route('request.staff.patrons.index') }}"
           class="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Clear</a>
    @endif
</form>

@if(! $showAll)
<p class="text-xs text-gray-500 mb-4">
    Showing patrons that are not found in Polaris, have field mismatches, have never been looked up, or may be duplicates.
    <a href="{{ route('request.staff.patrons.index', ['show_all' => 1]) }}" class="text-blue-600 hover:underline ml-1">Show all →</a>
</p>
@endif

{{-- Table --}}
<x-dcpl::card padding="p-0" class="overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <x-requests::sortable-th column="name_last" label="Name" />
                <x-requests::sortable-th column="barcode" label="Barcode" />
                <th class="px-4 py-3 text-left font-medium text-gray-600">Email / Phone</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Polaris</th>
                <x-requests::sortable-th column="requests_count" label="Requests" />
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($patrons as $patron)
            @php
                $neverLooked = ! $patron->polaris_lookup_attempted;
                $patronShowUrl = route('request.staff.patrons.show', $patron);
            @endphp
            <tr class="hover:bg-gray-50 cursor-pointer transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-blue-500"
                tabindex="0"
                role="link"
                aria-label="View patron {{ $patron->full_name }}"
                data-row-href="{{ $patronShowUrl }}"
                onclick="window.location.assign(this.dataset.rowHref)"
                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.assign(this.dataset.rowHref); }">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ $patron->name_last }}, {{ $patron->name_first }}</div>
                    <div class="text-xs text-gray-400">#{{ $patron->id }}</div>
                </td>
                <td class="px-4 py-3 font-mono text-gray-600 text-xs">{{ $patron->barcode }}</td>
                <td class="px-4 py-3">
                    @if($patron->email)
                        <div class="text-gray-700 text-xs">{{ $patron->email }}</div>
                    @endif
                    @if($patron->phone)
                        <div class="text-gray-500 text-xs">{{ $patron->phone }}</div>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($neverLooked)
                        <x-dcpl::badge variant="gray">Pending</x-dcpl::badge>
                    @elseif($patron->found_in_polaris)
                        <x-dcpl::badge variant="green">Found</x-dcpl::badge>
                    @else
                        <x-dcpl::badge variant="red">Not found</x-dcpl::badge>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $patron->requests_count }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                    {{ $showAll ? 'No patrons found.' : 'No flagged patrons — everything looks good.' }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</x-dcpl::card>

<div class="mt-4">
    {{ $patrons->links() }}
</div>
@endsection
