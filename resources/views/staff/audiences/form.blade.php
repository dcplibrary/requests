@extends('sfp::staff._layout')
@section('title', $audience->exists ? 'Edit Audience' : 'New Audience')
@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.audiences.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Audiences</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $audience->exists ? 'Edit Audience' : 'New Audience' }}</h1>
</div>

<div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
    <form method="POST" action="{{ $audience->exists ? route('sfp.staff.audiences.update', $audience) : route('sfp.staff.audiences.store') }}">
        @csrf
        @if($audience->exists) @method('PUT') @endif

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $audience->name) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">BiblioCommons Value <span class="text-red-500">*</span></label>
                <input type="text" name="bibliocommons_value" value="{{ old('bibliocommons_value', $audience->bibliocommons_value) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono">
                <p class="text-xs text-gray-400 mt-1">Used for BiblioCommons catalog filtering.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order <span class="text-red-500">*</span></label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $audience->sort_order ?? 0) }}" required min="0"
                       class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" id="active" value="1"
                       {{ old('active', $audience->active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                {{ $audience->exists ? 'Save Changes' : 'Create Audience' }}
            </button>
            <a href="{{ route('sfp.staff.audiences.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
@endsection
