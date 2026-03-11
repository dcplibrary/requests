@extends('requests::staff._layout')

@section('title', 'Edit Patron: ' . $patron->full_name)

@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.patrons.show', $patron) }}"
       class="text-sm text-blue-600 hover:underline">&larr; {{ $patron->full_name }}</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Edit Patron</h1>
</div>

<div class="max-w-lg space-y-6">

    {{-- Edit form --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Patron Fields</h2>

        <form method="POST" action="{{ route('request.staff.patrons.update', $patron) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="barcode" class="block text-sm font-medium text-gray-700 mb-1">
                        Barcode <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="barcode" name="barcode"
                           value="{{ old('barcode', $patron->barcode) }}" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono @error('barcode') border-red-400 @enderror">
                    @error('barcode')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="name_first" class="block text-sm font-medium text-gray-700 mb-1">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name_first" name="name_first"
                               value="{{ old('name_first', $patron->name_first) }}" required
                               class="w-full border border-gray-300 rounded px-3 py-2 text-sm @error('name_first') border-red-400 @enderror">
                        @error('name_first')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="name_last" class="block text-sm font-medium text-gray-700 mb-1">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name_last" name="name_last"
                               value="{{ old('name_last', $patron->name_last) }}" required
                               class="w-full border border-gray-300 rounded px-3 py-2 text-sm @error('name_last') border-red-400 @enderror">
                        @error('name_last')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                        Phone <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="phone" name="phone"
                           value="{{ old('phone', $patron->phone) }}" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm @error('phone') border-red-400 @enderror">
                    @error('phone')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $patron->email) }}"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm @error('email') border-red-400 @enderror">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                        class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                    Save Changes
                </button>
                <a href="{{ route('request.staff.patrons.show', $patron) }}"
                   class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    {{-- Polaris lookup section --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Polaris Lookup</h2>

        <div class="mb-4 text-sm">
            <span class="text-gray-500">Current status: </span>
            @if(! $patron->polaris_lookup_attempted)
                <span class="text-gray-700 font-medium">Pending (never attempted)</span>
            @elseif($patron->found_in_polaris)
                <span class="text-green-700 font-medium">Found</span>
                @if($patron->polaris_lookup_at)
                    <span class="text-gray-400 ml-1 text-xs">({{ $patron->polaris_lookup_at->diffForHumans() }})</span>
                @endif
            @else
                <span class="text-red-700 font-medium">Not found</span>
                @if($patron->polaris_lookup_at)
                    <span class="text-gray-400 ml-1 text-xs">(last checked {{ $patron->polaris_lookup_at->diffForHumans() }})</span>
                @endif
            @endif
        </div>

        <form method="POST" action="{{ route('request.staff.patrons.retrigger-polaris', $patron) }}">
            @csrf
            <button type="submit"
                    class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded border border-gray-300 hover:bg-gray-200"
                    onclick="return confirm('Reset lookup state and re-queue Polaris lookup for this patron?')">
                ↺ Re-trigger Polaris Lookup
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-2">
            Resets the lookup flag and re-queues the job. The barcode currently on record will be used.
        </p>
    </div>

</div>
@endsection
