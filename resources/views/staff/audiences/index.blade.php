@extends('requests::staff.settings._layout')
@section('title', 'Audiences')
@section('settings-content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Audiences</h1>
    <a href="{{ route('request.staff.audiences.create') }}"
       class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Audience</a>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">BiblioCommons Value</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Order</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Active</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($audiences as $audience)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900">{{ $audience->name }}</td>
                <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $audience->bibliocommons_value }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $audience->sort_order }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $audience->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $audience->active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                    <x-dcpl::icon-btn :href="route('request.staff.audiences.edit', $audience)" variant="edit" label="Edit" />
                    <x-dcpl::icon-btn :href="route('request.staff.audiences.delete', $audience)" variant="delete" label="Delete" />
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No audiences yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
