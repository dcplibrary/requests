@extends('sfp::staff._layout')
@section('title', 'Selector Groups')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Selector Groups</h1>
    <a href="{{ route('sfp.staff.groups.create') }}"
       class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Group</a>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Material Types</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Audiences</th>
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
                <td class="px-4 py-3 text-gray-600 text-xs">
                    {{ $group->materialTypes->pluck('name')->join(', ') ?: '—' }}
                </td>
                <td class="px-4 py-3 text-gray-600 text-xs">
                    {{ $group->audiences->pluck('name')->join(', ') ?: '—' }}
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $group->users->count() }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $group->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $group->active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex items-center justify-end gap-3">
                    <a href="{{ route('sfp.staff.groups.edit', $group) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                    <form method="POST" action="{{ route('sfp.staff.groups.destroy', $group) }}"
                          onsubmit="return confirm('Delete this group?')">
                        @csrf @method('DELETE')
                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No groups yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
