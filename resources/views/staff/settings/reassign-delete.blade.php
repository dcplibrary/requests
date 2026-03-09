@extends('sfp::staff.settings._layout')

@section('title', $title)

@section('settings-content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
    <p class="text-sm text-gray-500 mt-1">
        You must reassign related records before deletion.
    </p>
</div>

<div class="bg-white rounded-lg border border-gray-200 p-6 space-y-6">
    <div>
        <p class="text-sm text-gray-700">
            Deleting <span class="font-semibold text-gray-900">{{ $itemLabel }}</span> will update the following:
        </p>
        <ul class="mt-3 text-sm text-gray-600 space-y-1 list-disc list-inside">
            @foreach($impacts as $impact)
                <li>{{ $impact }}</li>
            @endforeach
        </ul>
    </div>

    @if(!empty($previews))
        <div class="pt-2 border-t border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Preview affected records</h2>
            <div class="space-y-4">
                @foreach($previews as $preview)
                    <div class="rounded-md border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-900">{{ $preview['title'] }}</p>
                            @if(!empty($preview['count']) && !empty($preview['count_label']))
                                <span class="text-xs text-gray-500">{{ $preview['count'] }} {{ $preview['count_label'] }}</span>
                            @endif
                        </div>

                        @if(empty($preview['items']))
                            <p class="mt-2 text-sm text-gray-500">No records.</p>
                        @else
                            <ul class="mt-2 text-sm text-gray-700 space-y-1">
                                @foreach($preview['items'] as $item)
                                    <li class="flex items-center gap-2">
                                        <span class="font-mono text-xs text-gray-500">{{ $item['mono'] ?? '' }}</span>
                                        @if(!empty($item['href']))
                                            <a class="text-blue-600 hover:underline" href="{{ $item['href'] }}" target="_blank" rel="noopener noreferrer">
                                                {{ $item['label'] }}
                                            </a>
                                        @else
                                            <span>{{ $item['label'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $deleteAction }}">
        @csrf
        @method('DELETE')

        @if(!empty($hasDependencies) && !empty($options))
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Reassign associated records to</label>
            <select name="reassign_to_id" required class="w-full max-w-md border border-gray-300 rounded px-3 py-2 text-sm">
                <option value="">Select…</option>
                @foreach($options as $opt)
                    <option value="{{ $opt['id'] }}" {{ old('reassign_to_id') == $opt['id'] ? 'selected' : '' }}>
                        {{ $opt['label'] }}
                    </option>
                @endforeach
            </select>
            @error('reassign_to_id')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        @elseif(!empty($hasDependencies) && empty($options))
        <p class="text-sm text-amber-700">You must create another item of this type before deleting (records need a reassignment target).</p>
        @endif

        @if(!empty($extraFields))
            <div class="pt-2 space-y-2">
                @foreach($extraFields as $field)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="{{ $field['name'] }}" value="1" {{ old($field['name'], $field['default'] ?? false) ? 'checked' : '' }}>
                        <span>{{ $field['label'] }}</span>
                    </label>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-3 pt-4">
            <a href="{{ $cancelHref }}" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                    onclick="return confirm('Delete {{ $itemLabel }}? This cannot be undone.')">
                Delete
            </button>
        </div>
    </form>
</div>
@endsection

