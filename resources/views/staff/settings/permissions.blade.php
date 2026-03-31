@extends('requests::staff.settings._layout')
@section('title', 'Permissions')
@section('settings-content')

<div class="mb-6">
    <h1 class="text-xl font-bold text-gray-900">Permissions</h1>
    <p class="mt-1 text-sm text-gray-500">Staff roles control what each user can see and do in the portal.</p>
</div>

{{-- Role reference --}}
<div class="space-y-4 mb-8">

    {{-- Admin --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Admin</span>
                <h3 class="font-semibold text-gray-900">Administrator</h3>
            </div>
            <span class="text-sm text-gray-400">{{ $roleCounts['admin'] ?? 0 }} active</span>
        </div>
        <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
            <li>Full access to all SFP and ILL requests</li>
            <li>View and manage all patrons and titles</li>
            <li>Access settings, users, groups, statuses, templates, and backups</li>
            <li>Can take all workflow actions (status changes, assignment, routing, deletion)</li>
        </ul>
    </div>

    {{-- Selector --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Selector</span>
                <h3 class="font-semibold text-gray-900">Selector</h3>
            </div>
            <span class="text-sm text-gray-400">{{ $roleCounts['selector'] ?? 0 }} active</span>
        </div>
        <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
            <li>Sees only the SFP requests matching their assigned selector group(s)</li>
            <li>ILL access is group-based — users in the designated ILL group can view and work ILL requests</li>
            <li>Can take workflow actions on requests visible to them</li>
            <li>No access to settings or user management</li>
        </ul>
    </div>

    {{-- Staff (view-only) --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Staff</span>
                <h3 class="font-semibold text-gray-900">Staff (view only)</h3>
            </div>
            <span class="text-sm text-gray-400">{{ $roleCounts['staff'] ?? 0 }} active</span>
        </div>
        <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
            <li>Read-only access to all SFP requests, ILL requests, Patrons, and Titles</li>
            <li>Cannot change statuses, claim or assign requests, merge patrons, or take any write actions</li>
            <li>No access to settings or user management</li>
            <li>Suitable for library staff who need to look up requests without processing them</li>
        </ul>
    </div>

</div>

{{-- Manage users link --}}
<div class="bg-gray-50 rounded-lg border border-gray-200 p-4 flex items-center justify-between">
    <p class="text-sm text-gray-700">Assign roles to staff members on the <strong>Users</strong> page.</p>
    <a href="{{ route('request.staff.users.index') }}"
       class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
        Manage Users
    </a>
</div>

@endsection
