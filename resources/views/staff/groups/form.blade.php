@extends('sfp::staff._layout')
@section('title', $group->exists ? 'Edit Group' : 'New Group')
@section('content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.groups.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Groups</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $group->exists ? 'Edit Group' : 'New Group' }}</h1>
</div>

<div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
    <form method="POST" action="{{ $group->exists ? route('sfp.staff.groups.update', $group) : route('sfp.staff.groups.store') }}">
        @csrf
        @if($group->exists) @method('PUT') @endif

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $group->name) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm resize-none">{{ old('description', $group->description) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Material Types</label>
                @forelse($materialTypes as $type)
                <div class="flex items-center gap-2 mb-1.5">
                    <input type="checkbox" name="material_types[]" id="mt_{{ $type->id }}" value="{{ $type->id }}"
                           {{ in_array($type->id, old('material_types', $group->materialTypes->pluck('id')->toArray())) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <label for="mt_{{ $type->id }}" class="text-sm text-gray-700">{{ $type->name }}</label>
                </div>
                @empty
                <p class="text-sm text-gray-400">No material types defined yet.</p>
                @endforelse
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Audiences</label>
                @forelse($audiences as $audience)
                <div class="flex items-center gap-2 mb-1.5">
                    <input type="checkbox" name="audiences[]" id="aud_{{ $audience->id }}" value="{{ $audience->id }}"
                           {{ in_array($audience->id, old('audiences', $group->audiences->pluck('id')->toArray())) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <label for="aud_{{ $audience->id }}" class="text-sm text-gray-700">{{ $audience->name }}</label>
                </div>
                @empty
                <p class="text-sm text-gray-400">No audiences defined yet.</p>
                @endforelse
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" id="active" value="1"
                       {{ old('active', $group->active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                {{ $group->exists ? 'Save Changes' : 'Create Group' }}
            </button>
            <a href="{{ route('sfp.staff.groups.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
@endsection
