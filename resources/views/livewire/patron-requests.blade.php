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
            <x-requests::limit-reached :count="$limitCount" :until="$limitUntil" />
        </div>
    @endif

    {{-- Results --}}
    @if($requests->isEmpty())

        <div class="bg-white rounded-lg border border-gray-200 p-8 shadow-sm text-center text-gray-500 text-sm">
            You haven't submitted any suggestions yet.
            <a href="{{ route('request.form') }}" class="ml-1 text-blue-600 hover:underline">Submit your first one →</a>
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
                        @php
                            $bcTitle = rawurlencode($req->submitted_title ?? '');
                            $bcAuthor = rawurlencode($req->submitted_author ?? '');
                            $bcQuery = '(title%3A(' . $bcTitle . ')%20AND%20contributor%3A(' . $bcAuthor . ')%20)';
                            $bcUrl = 'https://dcpl.bibliocommons.com/v2/search?custom_edit=false&query=' . $bcQuery . '&searchType=bl&suppress=true';
                        @endphp
                        <p class="font-semibold text-gray-900 text-sm truncate">
                            {{ $req->submitted_title }}
                        </p>
                        <p class="text-sm text-gray-500">
                            {{ $req->submitted_author }}
                        </p>
                        @if($req->fieldValueLabel('material_type'))
                            <p class="text-xs text-gray-400 mt-0.5">{{ $req->fieldValueLabel('material_type') }}</p>
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

                {{-- Catalog search (SFP only) --}}
                @if(($req->request_kind ?? 'sfp') === 'sfp' && $req->submitted_title)
                    <div class="mt-3">
                        <x-requests::external-link-btn :href="$bcUrl" label="Search the catalog" icon="globe" />
                        <p class="mt-1.5 text-xs text-gray-400">
                            If the title isn't in the catalog yet, check back later — it may still be on order.
                        </p>
                    </div>
                @endif

                {{-- Submitted date --}}
                <p class="mt-2 text-xs text-gray-400">
                    @if(($req->request_kind ?? 'sfp') === 'ill')
                        Interlibrary loan requested on {{ $req->created_at->format('F j, Y') }}
                    @else
                        Suggested for purchase on {{ $req->created_at->format('F j, Y') }}
                    @endif
                </p>
            </div>
            @endforeach
        </div>

    @endif

    <div class="mt-6 text-center">
        <a href="{{ route('request.form') }}" class="text-sm text-blue-600 hover:underline">
            Submit another suggestion →
        </a>
    </div>

</div>
