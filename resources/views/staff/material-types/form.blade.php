@extends('sfp::staff.settings._layout')
@section('title', $type->exists ? 'Edit Material Type' : 'New Material Type')
@section('settings-content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.material-types.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Material Types</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $type->exists ? 'Edit Material Type' : 'New Material Type' }}</h1>
</div>

<div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
    <form method="POST" action="{{ $type->exists ? route('request.staff.material-types.update', $type) : route('request.staff.material-types.store') }}">
        @csrf
        @if($type->exists) @method('PUT') @endif

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $type->name) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order <span class="text-red-500">*</span></label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $type->sort_order ?? 0) }}" required min="0"
                       class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="has_other_text" value="0">
                <input type="checkbox" name="has_other_text" id="has_other_text" value="1"
                       {{ old('has_other_text', $type->has_other_text ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="has_other_text" class="text-sm font-medium text-gray-700">Has "Other" text field</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" id="active" value="1"
                       {{ old('active', $type->active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="ill_enabled" value="0">
                <input type="checkbox" name="ill_enabled" id="ill_enabled" value="1"
                       {{ old('ill_enabled', $type->ill_enabled ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="ill_enabled" class="text-sm font-medium text-gray-700">Available on ILL form</label>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                {{ $type->exists ? 'Save Changes' : 'Create Type' }}
            </button>
            <a href="{{ route('request.staff.material-types.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
@endsection
