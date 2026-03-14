@extends('requests::staff.settings._layout')
@section('title', 'Users')
@section('settings-content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Users</h1>
    <p class="text-sm text-gray-500">Users are created automatically on first Entra login.</p>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <x-requests::sortable-th column="name" label="Name" />
                <x-requests::sortable-th column="email" label="Email" />
                <x-requests::sortable-th column="role" label="Role" />
                <th class="px-4 py-3 text-left font-medium text-gray-600">Groups</th>
                <x-requests::sortable-th column="active" label="Active" />
                <th class="px-4 py-3 text-left font-medium text-gray-600">Last Login</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($users as $user)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                        {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">
                    {{ $user->selectorGroups->pluck('name')->join(', ') ?: '—' }}
                </td>
                <td class="px-4 py-3">
                    <x-requests::status-pill :active="$user->active" />
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">
                    {{ $user->last_login_at?->diffForHumans() ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                    <x-requests::icon-btn :href="route('request.staff.users.edit', $user)" variant="edit" label="Edit" />
                    <x-requests::icon-btn :href="route('request.staff.users.remove', $user)" variant="remove" label="Remove" />
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No users yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
