@extends('requests::staff.settings._layout')
@section('title', 'Selector Groups')
@section('settings-content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Selector Groups</h1>
    <a href="{{ route('request.staff.groups.create') }}"
       class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Group</a>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                @foreach($filterableFields as $ff)
                <th class="px-4 py-3 text-left font-medium text-gray-600">{{ $ff->label }}</th>
                @endforeach
                <th class="px-4 py-3 text-left font-medium text-gray-600">Members</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Active</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($groups as $group)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ $group->name }}</div>
                    @if($group->description)
                        <div class="text-xs text-gray-400">{{ $group->description }}</div>
                    @endif
                </td>
                @foreach($filterableFields as $ff)
                <td class="px-4 py-3 text-gray-600 text-xs">
                    {{ $group->fieldOptions->filter(fn($o) => $o->field?->key === $ff->key)->pluck('name')->join(', ') ?: '—' }}
                </td>
                @endforeach
                <td class="px-4 py-3 text-gray-600">{{ $group->users->count() }}</td>
                <td class="px-4 py-3">
                    <x-requests::status-pill :active="$group->active" />
                </td>
                <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                    <x-requests::icon-btn :href="route('request.staff.groups.edit', $group)" variant="edit" label="Edit" />
                    <x-requests::icon-form-btn :action="route('request.staff.groups.destroy', $group)" label="Delete" confirm="Delete this group?" />
                </td>
            </tr>
            @empty
            <tr><td colspan="{{ 4 + count($filterableFields) }}" class="px-4 py-10 text-center text-gray-400">No groups yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
