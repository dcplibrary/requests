@extends('sfp::staff.settings._layout')
@section('title', 'Request Statuses')
@section('settings-content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Request Statuses</h1>
    <a href="{{ route('sfp.staff.statuses.create') }}"
       class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Status</a>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Color</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Terminal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Order</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Active</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($statuses as $status)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $status->color }};"></span>
                        <span class="font-medium text-gray-900">{{ $status->name }}</span>
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $status->color }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $status->is_terminal ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $status->sort_order }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $status->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $status->active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex items-center justify-end gap-3">
                    <a href="{{ route('sfp.staff.statuses.edit', $status) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                    <form method="POST" action="{{ route('sfp.staff.statuses.destroy', $status) }}"
                          onsubmit="return confirm('Delete this status?')">
                        @csrf @method('DELETE')
                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No statuses yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
