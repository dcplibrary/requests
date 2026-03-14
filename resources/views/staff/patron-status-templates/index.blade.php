@extends('requests::staff.settings._layout')
@section('title', 'Patron Status Templates')
@section('settings-content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Patron Status Templates</h1>
    <a href="{{ route('request.staff.patron-status-templates.create') }}"
       class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Template</a>
</div>

@if(session('success'))
<div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <x-requests::sortable-th column="name" label="Name" />
                <th class="px-4 py-3 text-left font-medium text-gray-600">Subject</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Statuses</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Material types</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Default</th>
                <x-requests::sortable-th column="enabled" label="Enabled" />
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($templates as $tpl)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-900">{{ $tpl->name }}</td>
                <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $tpl->subject }}">{{ $tpl->subject }}</td>
                <td class="px-4 py-3 text-gray-600">
                    @if($tpl->requestStatuses->isEmpty())
                        <span class="text-gray-400">—</span>
                    @else
                        {{ $tpl->requestStatuses->pluck('name')->join(', ') }}
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">
                    @if($tpl->fieldOptions->isEmpty())
                        <span class="text-gray-400">All</span>
                    @else
                        {{ $tpl->fieldOptions->pluck('name')->join(', ') }}
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($tpl->is_default)
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Default</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $tpl->enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $tpl->enabled ? 'Yes' : 'No' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                    <x-requests::icon-btn :href="route('request.staff.patron-status-templates.edit', $tpl)" variant="edit" label="Edit" />
                    <x-requests::icon-btn :href="route('request.staff.patron-status-templates.delete', $tpl)" variant="delete" label="Delete" />
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No templates yet. Create one to send different emails by status and material type.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="mt-4 text-xs text-gray-500">Each template is sent when a request’s status changes to one of its selected statuses (and matches material type if set). Leave material types empty for “all”. One template can be the default fallback. Only statuses with “Notify Patron” checked (in Statuses) will trigger email. The email footer is set in Notifications → General.</p>
@endsection
