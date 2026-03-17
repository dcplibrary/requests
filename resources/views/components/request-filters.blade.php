@props([
    'currentKind'       => null,
    'filters'           => [],
    'statuses',
    'assignmentEnabled' => false,
    'showCompleted'     => false,
])

@php
    // Build the toggle URL: preserves all current filter params, flips show_completed.
    $baseParams = collect($filters)
        ->filter(fn ($v) => $v !== null && $v !== '')
        ->all();
    if ($currentKind) {
        $baseParams['kind'] = $currentKind;
    }
    $toggleUrl = route(
        'request.staff.requests.index',
        $showCompleted ? $baseParams : array_merge($baseParams, ['show_completed' => '1'])
    );

    // Clear link strips all filters (keeps kind).
    $clearParams = $currentKind ? ['kind' => $currentKind] : [];
@endphp

<form method="GET" class="bg-white rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">

    @if($currentKind)
        <input type="hidden" name="kind" value="{{ $currentKind }}">
    @endif

    {{-- Preserve show_completed across filter submissions --}}
    @if($showCompleted)
        <input type="hidden" name="show_completed" value="1">
    @endif

    @if($assignmentEnabled)
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Assigned</label>
        <select name="assigned" class="text-sm border border-gray-300 rounded px-2 py-1.5">
            <option value="">Any</option>
            <option value="me" {{ ($filters['assigned'] ?? '') === 'me' ? 'selected' : '' }}>Me</option>
            <option value="unassigned" {{ ($filters['assigned'] ?? '') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
        </select>
    </div>
    @endif

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
        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
               placeholder="Title, author, barcode…"
               class="text-sm border border-gray-300 rounded px-2 py-1.5 w-48">
    </div>

    <button type="submit"
            class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
        Filter
    </button>

    @if(array_filter($filters))
        <a href="{{ route('request.staff.requests.index', $clearParams) }}"
           class="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
            Clear
        </a>
    @endif

    {{-- Show Completed toggle — lives outside the form submit flow, just a link --}}
    <a href="{{ $toggleUrl }}"
       class="ml-auto px-3 py-1.5 text-sm rounded border transition-colors
              {{ $showCompleted
                    ? 'bg-gray-700 text-white border-gray-700 hover:bg-gray-600'
                    : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-50 hover:text-gray-700' }}">
        @if($showCompleted)
            <span aria-hidden="true">✓</span> Showing Completed
        @else
            Show Completed
        @endif
    </a>

</form>
