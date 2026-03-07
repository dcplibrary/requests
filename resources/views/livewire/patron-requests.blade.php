<div class="max-w-2xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">My Requests</h1>
        <button
            type="button"
            wire:click="logout"
            class="text-sm text-gray-500 hover:text-gray-700 hover:underline"
        >
            Sign out
        </button>
    </div>
    <p class="text-sm text-gray-500 mb-8">
        Suggestions you've submitted and their current status.
    </p>

    {{-- Submission limit warning --}}
    @if($limitReached)
        <div class="mb-6">
            <x-sfp::limit-reached :count="$limitCount" :until="$limitUntil" />
        </div>
    @endif

    {{-- Results --}}
    @if($requests->isEmpty())

        <div class="bg-white rounded-lg border border-gray-200 p-8 shadow-sm text-center text-gray-500 text-sm">
            You haven't submitted any suggestions yet.
            <a href="{{ route('sfp.form') }}" class="ml-1 text-blue-600 hover:underline">Submit your first one →</a>
        </div>

    @else

        <div class="mb-4">
            <p class="text-sm text-gray-600">
                {{ $requests->count() }} suggestion{{ $requests->count() === 1 ? '' : 's' }} found.
            </p>
        </div>

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
                        <p class="text-xs text-gray-400 mt-0.5">
                            Kind: <span class="font-mono">{{ strtoupper($req->request_kind ?? 'sfp') }}</span>
                        </p>
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

                @if(($req->request_kind ?? 'sfp') === 'sfp')
                    <div class="mt-3 flex items-center justify-between">
                        <p class="text-xs text-gray-400">
                            Need ILL instead? You can convert this request.
                        </p>
                        <button type="button"
                                wire:click="convertToIll({{ $req->id }})"
                                class="text-xs px-3 py-1.5 rounded bg-purple-600 text-white hover:bg-purple-700"
                                onclick="return confirm('Convert this request to Interlibrary Loan?')">
                            Convert to ILL
                        </button>
                    </div>
                @endif

                {{-- Submitted date --}}
                <p class="mt-2 text-xs text-gray-400">
                    Submitted {{ $req->created_at->format('F j, Y') }}
                </p>
            </div>
            @endforeach
        </div>

    @endif

    <div class="mt-6 text-center">
        <a href="{{ route('sfp.form') }}" class="text-sm text-blue-600 hover:underline">
            Submit another suggestion →
        </a>
    </div>

</div>
