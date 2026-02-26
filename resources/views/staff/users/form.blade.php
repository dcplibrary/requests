@extends('sfp::staff.settings._layout')
@section('title', 'Edit User')
@section('settings-content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.users.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Users</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Edit User</h1>
</div>

<div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
    <div class="mb-5 pb-4 border-b border-gray-100">
        <p class="font-medium text-gray-900">{{ $user->name }}</p>
        <p class="text-sm text-gray-500">{{ $user->email }}</p>
    </div>

    <form method="POST" action="{{ route('sfp.staff.users.update', $user) }}">
        @csrf @method('PUT')

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                <select name="role" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    <option value="selector" {{ old('role', $user->role) === 'selector' ? 'selected' : '' }}>Selector</option>
                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" id="active" value="1"
                       {{ old('active', $user->active) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Selector Groups</label>
                @forelse($groups as $group)
                <div class="flex items-center gap-2 mb-1.5">
                    <input type="checkbox" name="groups[]" id="group_{{ $group->id }}" value="{{ $group->id }}"
                           {{ in_array($group->id, old('groups', $user->selectorGroups->pluck('id')->toArray())) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <label for="group_{{ $group->id }}" class="text-sm text-gray-700">{{ $group->name }}</label>
                </div>
                @empty
                <p class="text-sm text-gray-400">No groups defined yet.</p>
                @endforelse
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save Changes</button>
            <a href="{{ route('sfp.staff.users.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
@endsection
