@extends('sfp::staff._layout')

@section('title', 'Patron: ' . $patron->full_name)

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.patrons.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Patrons</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $patron->full_name }}</h1>
    <span class="text-sm text-gray-400 font-mono">#{{ $patron->id }}</span>
</div>

{{-- Duplicate warning panel --}}
@if($suspects->isNotEmpty())
<div class="mb-6 rounded-lg bg-orange-50 border border-orange-200 px-5 py-4">
    <p class="text-sm font-semibold text-orange-800 mb-2">Possible duplicate patrons detected</p>
    <ul class="space-y-1.5">
        @foreach($suspects as $suspect)
        <li class="text-sm text-orange-700 flex items-center gap-3">
            <a href="{{ route('sfp.staff.patrons.show', $suspect) }}" class="underline hover:text-orange-900 font-medium">
                {{ $suspect->full_name }}
            </a>
            <span class="text-orange-500">·</span>
            <span class="font-mono text-xs">{{ $suspect->barcode }}</span>
            <span class="text-orange-500">·</span>
            <span class="text-xs">{{ $suspect->requests()->count() }} request(s)</span>
            <span class="text-orange-500">·</span>
            <a href="{{ route('sfp.staff.patrons.merge-confirm', $patron) }}?target_id={{ $suspect->id }}"
               class="text-xs underline text-orange-600 hover:text-orange-900">
                Keep this patron, merge #{{ $suspect->id }} into it &rarr;
            </a>
        </li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column (2/3) --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Submitted data --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Submitted Data</h2>
                <a href="{{ route('sfp.staff.patrons.edit', $patron) }}"
                   class="text-xs px-3 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                    Edit
                </a>
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
                <form method="POST" action="{{ route('sfp.staff.patrons.retrigger-polaris', $patron) }}">
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
                            <a href="{{ route('sfp.staff.requests.show', $req) }}"
                               class="text-blue-600 hover:underline text-xs">View</a>
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

        {{-- Merge card --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Merge Patron</h2>
            <p class="text-xs text-gray-500 mb-3">
                All requests from <strong>this patron (#{{ $patron->id }})</strong> will be moved to the winner, and this record will be deleted.
            </p>
            <form method="GET" action="{{ route('sfp.staff.patrons.merge-confirm', $patron) }}">
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
            </dl>
        </div>

    </div>
</div>
@endsection
