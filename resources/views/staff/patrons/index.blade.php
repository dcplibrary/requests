@extends('sfp::staff._layout')

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
        <a href="{{ route('sfp.staff.patrons.index') }}"
           class="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Clear</a>
    @endif
</form>

@if(! $showAll)
<p class="text-xs text-gray-500 mb-4">
    Showing patrons that are not found in Polaris, have field mismatches, have never been looked up, or may be duplicates.
    <a href="{{ route('sfp.staff.patrons.index', ['show_all' => 1]) }}" class="text-blue-600 hover:underline ml-1">Show all →</a>
</p>
@endif

{{-- Table --}}
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Barcode</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Email / Phone</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Polaris</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Requests</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Flags</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($patrons as $patron)
            @php
                $isSuspect  = in_array($patron->id, $suspectedDuplicateIds);
                $hasMismatch = $patron->name_first_matches === false
                    || $patron->name_last_matches === false
                    || $patron->phone_matches === false
                    || $patron->email_matches === false;
                $notFound   = $patron->found_in_polaris === false && $patron->polaris_lookup_attempted;
                $neverLooked = ! $patron->polaris_lookup_attempted;
            @endphp
            <tr class="hover:bg-gray-50">
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
                        <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Pending</span>
                    @elseif($patron->found_in_polaris)
                        <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Found</span>
                    @else
                        <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700">Not found</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $patron->requests_count }}</td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        @if($hasMismatch)
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">Mismatch</span>
                        @endif
                        @if($isSuspect)
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-orange-100 text-orange-700">Possible duplicate</span>
                        @endif
                        @if($notFound)
                            <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-600">Not in Polaris</span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('sfp.staff.patrons.show', $patron) }}"
                       class="text-blue-600 hover:underline text-xs font-medium">View</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                    {{ $showAll ? 'No patrons found.' : 'No flagged patrons — everything looks good.' }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $patrons->links() }}
</div>
@endsection
