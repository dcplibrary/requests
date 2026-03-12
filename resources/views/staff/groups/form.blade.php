@extends('requests::staff.settings._layout')
@section('title', $group->exists ? 'Edit Group' : 'New Group')
@section('settings-content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.groups.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Groups</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $group->exists ? 'Edit Group' : 'New Group' }}</h1>
</div>

<div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
    <form method="POST" action="{{ $group->exists ? route('request.staff.groups.update', $group) : route('request.staff.groups.store') }}">
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
            @foreach($filterableFields as $ff)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ $ff->label }}</label>
                @php
                    $selectedIds = old(
                        'field_options.' . $ff->key,
                        $group->fieldOptions->filter(fn($o) => $o->field?->key === $ff->key)->pluck('id')->toArray()
                    );
                @endphp
                @forelse($ff->options as $option)
                <div class="flex items-center gap-2 mb-1.5">
                    <input type="checkbox" name="field_options[{{ $ff->key }}][]" id="fo_{{ $ff->key }}_{{ $option->id }}" value="{{ $option->id }}"
                           {{ in_array($option->id, $selectedIds) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <label for="fo_{{ $ff->key }}_{{ $option->id }}" class="text-sm text-gray-700">{{ $option->name }}</label>
                </div>
                @empty
                <p class="text-sm text-gray-400">No {{ strtolower($ff->label) }} options defined yet.</p>
                @endforelse
            </div>
            @endforeach
            @if($filterableFields->isEmpty())
            <p class="text-sm text-gray-400">No filterable fields available. Mark select/radio fields as "Filterable" in Form Fields settings.</p>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notification Emails</label>
                <textarea name="notification_emails" rows="3"
                          class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono resize-y"
                          placeholder="one@example.com, two@example.com&#10;(comma or newline separated)"
                >{{ old('notification_emails', $group->notification_emails) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">
                    When a new request matches this group's field options, a routing email will be sent to these addresses.
                    Separate multiple addresses with commas or line breaks.
                </p>
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
            <a href="{{ route('request.staff.groups.index') }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
@endsection
