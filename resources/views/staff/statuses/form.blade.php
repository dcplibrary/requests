@extends('sfp::staff._layout')
@section('title', $status->exists ? 'Edit Status' : 'New Status')
@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.statuses.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Statuses</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $status->exists ? 'Edit Status' : 'New Status' }}</h1>
</div>

<div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
    <form method="POST" action="{{ $status->exists ? route('sfp.staff.statuses.update', $status) : route('sfp.staff.statuses.store') }}">
        @csrf
        @if($status->exists) @method('PUT') @endif

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $status->name) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-2">
                    <input type="color" name="color" value="{{ old('color', $status->color ?? '#6b7280') }}"
                           class="w-10 h-9 border border-gray-300 rounded cursor-pointer p-0.5">
                    <input type="text" id="color_text" value="{{ old('color', $status->color ?? '#6b7280') }}"
                           class="w-32 border border-gray-300 rounded px-3 py-2 text-sm font-mono"
                           oninput="document.querySelector('input[name=color]').value=this.value">
                </div>
                <script>
                    document.querySelector('input[name=color]').addEventListener('input', function() {
                        document.getElementById('color_text').value = this.value;
                    });
                </script>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order <span class="text-red-500">*</span></label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $status->sort_order ?? 0) }}" required min="0"
                       class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_terminal" value="0">
                <input type="checkbox" name="is_terminal" id="is_terminal" value="1"
                       {{ old('is_terminal', $status->is_terminal ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="is_terminal" class="text-sm font-medium text-gray-700">Terminal (final state)</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" id="active" value="1"
                       {{ old('active', $status->active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                {{ $status->exists ? 'Save Changes' : 'Create Status' }}
            </button>
            <a href="{{ route('sfp.staff.statuses.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
@endsection
