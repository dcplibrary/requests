@extends('requests::staff.settings._layout')
@section('title', 'Request Statuses')
@section('settings-content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Request Statuses</h1>
    <a href="{{ route('request.staff.statuses.create') }}"
       class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Status</a>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <x-requests::sortable-th column="name" label="Name" />
                <th class="px-4 py-3 text-left font-medium text-gray-600">Icon</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Terminal</th>
                <x-requests::sortable-th column="sort_order" label="Order" />
                <th class="px-4 py-3 text-left font-medium text-gray-600">Notify</th>
                <x-requests::sortable-th column="active" label="Active" />
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
                <td class="px-4 py-3 text-gray-500">
                    @if($status->icon)
                        <x-requests::status-icon :name="$status->icon" class="w-5 h-5" />
                    @else
                        <span class="text-gray-300">&mdash;</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $status->is_terminal ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $status->sort_order }}</td>
                <td class="px-4 py-3">
                    <x-requests::status-pill :active="$status->notify_patron" active-label="On" inactive-label="Off" />
                </td>
                <td class="px-4 py-3">
                    <x-requests::status-pill :active="$status->active" />
                </td>
                <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                    <x-requests::icon-btn :href="route('request.staff.statuses.edit', $status)" variant="edit" label="Edit" />
                    <x-requests::icon-btn :href="route('request.staff.statuses.delete', $status)" variant="delete" label="Delete" />
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No statuses yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
