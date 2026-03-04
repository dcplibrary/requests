<div class="max-w-2xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-gray-900 mb-2">Check Your Requests</h1>
    <p class="text-sm text-gray-500 mb-8">
        Enter your library card number and last name to see the titles you've suggested and their current status.
    </p>

    {{-- ── Lookup Form ─────────────────────────────────────────────────────── --}}
    @if(! $searched)

        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
            <div class="space-y-4">

                {{-- Barcode --}}
                <div>
                    <label for="lookup-barcode" class="block text-sm font-medium text-gray-700 mb-1">
                        Library Card Number <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="lookup-barcode"
                        wire:model="barcode"
                        autocomplete="off"
                        class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('barcode') ? 'border-red-500' : 'border-gray-300' }}"
                        aria-required="true"
                    />
                    @error('barcode')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Last name --}}
                <div>
                    <label for="lookup-last" class="block text-sm font-medium text-gray-700 mb-1">
                        Last Name <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="lookup-last"
                        wire:model="last_name"
                        autocomplete="family-name"
                        class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('last_name') ? 'border-red-500' : 'border-gray-300' }}"
                        aria-required="true"
                    />
                    @error('last_name')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-1">
                    <button
                        type="button"
                        wire:click="lookup"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <span wire:loading.remove wire:target="lookup">Look Up My Requests</span>
                        <span wire:loading wire:target="lookup">Searching…</span>
                    </button>
                </div>

            </div>
        </div>

    {{-- ── Not Found ────────────────────────────────────────────────────────── --}}
    @elseif($notFound)

        <div class="bg-white rounded-lg border border-red-200 p-6 shadow-sm text-center">
            <div class="mx-auto mb-3 flex items-center justify-center w-12 h-12 rounded-full bg-red-100">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>
            <h2 class="text-base font-semibold text-gray-800 mb-1">No records found</h2>
            <p class="text-sm text-gray-500 mb-5">
                We couldn't find any requests matching that card number and last name.
                Please double-check your information and try again.
            </p>
            <button type="button" wire:click="startOver"
                    class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                Try Again
            </button>
        </div>

    {{-- ── Results ──────────────────────────────────────────────────────────── --}}
    @elseif($requests !== null)

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">
                @if($requests->isEmpty())
                    No suggestions on file yet.
                @else
                    {{ $requests->count() }} suggestion{{ $requests->count() === 1 ? '' : 's' }} found.
                @endif
            </p>
            <button type="button" wire:click="startOver"
                    class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
                ← Start Over
            </button>
        </div>

        @if($requests->isEmpty())

            <div class="bg-white rounded-lg border border-gray-200 p-8 shadow-sm text-center text-gray-500 text-sm">
                You haven't submitted any suggestions yet.
                <a href="{{ route('sfp.form') }}" class="ml-1 text-blue-600 hover:underline">Submit your first one →</a>
            </div>

        @else

            <div class="space-y-3">
                @foreach($requests as $req)
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                        <div class="flex items-start justify-between gap-4">

                            {{-- Title / Author / Type --}}
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 text-sm truncate">
                                    {{ $req->submitted_title }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $req->submitted_author }}
                                </p>
                                @if($req->materialType)
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $req->materialType->name }}</p>
                                @endif
                            </div>

                            {{-- Status badge --}}
                            @if($req->status)
                                <span
                                    class="shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold text-white"
                                    style="background-color: {{ $req->status->color ?? '#6b7280' }}"
                                >
                                    {{ $req->status->name }}
                                </span>
                            @endif

                        </div>

                        {{-- Submitted date --}}
                        <p class="mt-2 text-xs text-gray-400">
                            Submitted {{ $req->created_at->format('F j, Y') }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 text-center">
                <a href="{{ route('sfp.form') }}" class="text-sm text-blue-600 hover:underline">
                    Submit another suggestion →
                </a>
            </div>

        @endif

    @endif

</div>
