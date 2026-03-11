@extends('requests::staff._layout')

@section('title', 'Confirm Patron Merge')

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.patrons.show', $loser) }}"
       class="text-sm text-blue-600 hover:underline">&larr; Back</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Confirm Patron Merge</h1>
</div>

{{-- Warning banner --}}
<div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-5 py-4 text-sm text-red-800">
    <strong>This action cannot be undone.</strong>
    All requests from the deleted patron will be reassigned to the winner, and the deleted record cannot be recovered.
</div>

{{-- Side-by-side comparison --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    {{-- Loser — will be deleted --}}
    <div class="bg-white rounded-lg border-2 border-red-300 p-5">
        <div class="flex items-center gap-2 mb-4">
            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
                Will be deleted
            </span>
            <span class="text-xs text-gray-400 font-mono">#{{ $loser->id }}</span>
        </div>
        <dl class="space-y-2.5 text-sm">
            <div>
                <dt class="text-xs text-gray-500">Name</dt>
                <dd class="font-medium text-gray-900">{{ $loser->full_name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Barcode</dt>
                <dd class="font-mono text-gray-700">{{ $loser->barcode }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Phone</dt>
                <dd class="text-gray-700">{{ $loser->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Email</dt>
                <dd class="text-gray-700">{{ $loser->email ?: '—' }}</dd>
            </div>
            <div class="pt-2 border-t border-gray-100">
                <dt class="text-xs text-gray-500">Requests</dt>
                <dd class="font-semibold text-red-700">
                    {{ $loser->requests->count() }} request(s) will be moved
                </dd>
            </div>
        </dl>
    </div>

    {{-- Winner — will be kept --}}
    <div class="bg-white rounded-lg border-2 border-green-300 p-5">
        <div class="flex items-center gap-2 mb-4">
            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-700">
                Will be kept (winner)
            </span>
            <span class="text-xs text-gray-400 font-mono">#{{ $winner->id }}</span>
        </div>
        <dl class="space-y-2.5 text-sm">
            <div>
                <dt class="text-xs text-gray-500">Name</dt>
                <dd class="font-medium text-gray-900">{{ $winner->full_name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Barcode</dt>
                <dd class="font-mono text-gray-700">{{ $winner->barcode }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Phone</dt>
                <dd class="text-gray-700">{{ $winner->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Email</dt>
                <dd class="text-gray-700">{{ $winner->email ?: '—' }}</dd>
            </div>
            <div class="pt-2 border-t border-gray-100">
                <dt class="text-xs text-gray-500">Requests after merge</dt>
                <dd class="font-semibold text-green-700">
                    {{ $winner->requests->count() }} existing
                    + {{ $loser->requests->count() }} incoming
                    = {{ $winner->requests->count() + $loser->requests->count() }} total
                </dd>
            </div>
        </dl>
    </div>

</div>

{{-- Action buttons --}}
<div class="flex gap-4 items-center">
    <form method="POST" action="{{ route('request.staff.patrons.merge', $loser) }}">
        @csrf
        <input type="hidden" name="target_id" value="{{ $winner->id }}">
        <button type="submit"
                class="px-6 py-2.5 bg-red-600 text-white text-sm font-semibold rounded hover:bg-red-700">
            Confirm: delete #{{ $loser->id }}, move requests to #{{ $winner->id }}
        </button>
    </form>
    <a href="{{ route('request.staff.patrons.show', $loser) }}"
       class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
        Cancel
    </a>
</div>
@endsection
