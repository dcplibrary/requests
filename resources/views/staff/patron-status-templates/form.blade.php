@extends('sfp::staff.settings._layout')
@section('title', $template->exists ? 'Edit Template' : 'New Template')
@section('settings-content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.settings.notifications', ['tab' => 'emails']) }}" class="text-sm text-blue-600 hover:underline">&larr; Emails</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $template->exists ? 'Edit template' : 'New template' }}</h1>
</div>

@if(session('success'))
<div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{{ session('success') }}</div>
@endif

@php
    $subjectTokens = array_values(array_diff($availableTokens ?? [], $subjectExcludedTokens ?? []));
@endphp

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <form method="POST" action="{{ $template->exists ? route('request.staff.patron-status-templates.update', $template) : route('request.staff.patron-status-templates.store') }}">
        @csrf
        @if($template->exists) @method('PUT') @endif

        <div class="divide-y divide-gray-100">
            {{-- Name + Enabled (same row pattern: label left, control right) --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Template name</label>
                    <p class="text-xs text-gray-400 mt-0.5">Internal label for this template.</p>
                </div>
                <div class="flex-1 min-w-0 flex items-center gap-4">
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                           class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm" placeholder="e.g. On order notification">
                    <label class="flex items-center gap-2 shrink-0">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" id="enabled" value="1"
                               {{ old('enabled', $template->enabled ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-gray-300 text-blue-600">
                        <span class="text-sm font-medium text-gray-700">Enable</span>
                    </label>
                </div>
            </div>

            {{-- Subject: label left, subject input + Insert tokens below (same as Notifications) --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Patron Status — Subject</label>
                    <p class="text-xs text-gray-400 mt-0.5">Subject line for this notification.</p>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="text" id="subject-field" name="subject" value="{{ old('subject', $template->subject) }}" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    @if(!empty($subjectTokens))
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="text-xs text-gray-400">Insert:</span>
                        @foreach($subjectTokens as $token)
                        <button type="button" onclick="sfpInsertToken('{{ $token }}', 'subject-field')"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">
                            {{ $token }}
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Email body: label left, Trix + Insert tokens below (same as Notifications) --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Patron Status — Email Body</label>
                    <p class="text-xs text-gray-400 mt-0.5">Enter email body HTML. Footer is added automatically.</p>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="hidden" id="body-field" name="body" value="{{ old('body', $template->body) }}">
                    <trix-editor input="body-field"
                                 class="trix-content border border-gray-300 rounded bg-white text-sm"
                                 style="min-height: 480px; height: 480px;"></trix-editor>
                    @if(!empty($availableTokens))
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="text-xs text-gray-400">Insert:</span>
                        @foreach($availableTokens as $token)
                        <button type="button" onclick="sfpTrixInsert('{{ $token }}', 'body-field')"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">
                            {{ $token }}
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Triggered by statuses --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Triggered by statuses</label>
                    <p class="text-xs text-gray-400 mt-0.5">Select status(es) that send this email. Only statuses with “Notify Patron” checked (in Statuses) will send.</p>
                </div>
                <div class="flex-1 min-w-0">
                    <select id="status-ids-select" name="status_ids[]" multiple class="w-full border border-gray-300 rounded px-3 py-2 text-sm" size="{{ min(8, ($requestStatuses->count() ?: 1) + 1) }}">
                        @foreach($requestStatuses as $st)
                        <option value="{{ $st->id }}" {{ in_array($st->id, old('status_ids', $template->requestStatuses->pluck('id')->all())) ? 'selected' : '' }}>{{ $st->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="Array.from(document.getElementById('status-ids-select').options).forEach(o => o.selected = false)"
                            class="mt-1.5 text-xs text-gray-400 hover:text-red-500 hover:underline">Clear selection</button>
                </div>
            </div>

            {{-- Material types --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Material types</label>
                    <p class="text-xs text-gray-400 mt-0.5">Optional. Leave empty to use for all material types. Or select specific types so this template only sends for those.</p>
                </div>
                <div class="flex-1 min-w-0">
                    <select id="material-type-ids-select" name="material_type_ids[]" multiple class="w-full border border-gray-300 rounded px-3 py-2 text-sm" size="{{ min(8, ($materialTypes->count() ?: 1) + 1) }}">
                        @foreach($materialTypes as $mt)
                        <option value="{{ $mt->id }}" {{ in_array($mt->id, old('material_type_ids', $template->materialTypes->pluck('id')->all())) ? 'selected' : '' }}>{{ $mt->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="Array.from(document.getElementById('material-type-ids-select').options).forEach(o => o.selected = false)"
                            class="mt-1.5 text-xs text-gray-400 hover:text-red-500 hover:underline">Clear selection</button>
                </div>
            </div>

            {{-- Default --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Default template</label>
                    <p class="text-xs text-gray-400 mt-0.5">Use as fallback when no other template matches the status (and material type). Only one template can be default.</p>
                </div>
                <div class="flex-1 min-w-0 flex items-center gap-2">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" id="is_default" value="1"
                           {{ old('is_default', $template->is_default ?? false) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <label for="is_default" class="text-sm font-medium text-gray-700">Use as default</label>
                </div>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                {{ $template->exists ? 'Save changes' : 'Create template' }}
            </button>
            <a href="{{ route('request.staff.settings.notifications', ['tab' => 'emails']) }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>

<script>
function sfpInsertToken(token, id) {
    var el = document.getElementById(id);
    if (!el) return;
    var s = el.selectionStart ?? el.value.length, e = el.selectionEnd ?? el.value.length;
    el.value = el.value.slice(0, s) + token + el.value.slice(e);
    el.selectionStart = el.selectionEnd = s + token.length;
    el.focus();
}
function sfpTrixInsert(token, inputId) {
    var trix = document.querySelector('trix-editor[input="' + inputId + '"]');
    if (trix && trix.editor) { trix.editor.insertString(token); trix.focus(); }
}
</script>
@endsection
