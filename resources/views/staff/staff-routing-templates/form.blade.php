@extends('requests::staff.settings._layout')
@section('title', $template->exists ? 'Edit staff routing template' : 'New staff routing template')
@section('settings-content')
<div class="mb-6 flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <a href="{{ route('request.staff.settings.notifications', ['tab' => 'emails']) }}" class="text-sm text-blue-600 hover:underline">&larr; Emails</a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-900">{{ $template->exists ? 'Edit staff routing template' : 'New staff routing template' }}</h1>
    </div>
    @if($template->exists)
    <div class="flex items-center gap-2">
        <a href="{{ route('request.staff.staff-routing-templates.preview', $template) }}"
           class="whitespace-nowrap inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-700"
           onclick="try { window.open(this.href, 'sfpStaffTemplatePreview', 'width=680,height=620,scrollbars=yes'); return false; } catch (e) { return true; }">
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
            </svg>
            Preview in browser
        </a>
        <form method="POST" action="{{ route('request.staff.staff-routing-templates.test', $template) }}" class="flex items-center gap-2">
            @csrf
            <span class="text-xs font-medium text-gray-600">Send test to</span>
            <input type="email" name="email"
                   placeholder="you@example.com"
                   value="{{ old('email', auth()->user()?->email) }}"
                   class="border border-gray-300 rounded px-3 py-1.5 text-sm w-52 @error('email') border-red-400 @enderror">
            <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 whitespace-nowrap">
                Send Test
            </button>
        </form>
    </div>
    @endif
</div>
@error('email')<p class="mb-4 text-sm text-red-600">{{ $message }}</p>@enderror

@php
    $subjectTokens = array_values(array_diff($availableTokens ?? [], $subjectExcludedTokens ?? []));
@endphp

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <form method="POST" action="{{ $template->exists ? route('request.staff.staff-routing-templates.update', $template) : route('request.staff.staff-routing-templates.store') }}">
        @csrf
        @if($template->exists) @method('PUT') @endif

        <div class="divide-y divide-gray-100">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Selector group <span class="text-red-500">*</span></label>
                    <p class="text-xs text-gray-400 mt-0.5">New requests that match this group receive this email (to the group's notification addresses).</p>
                </div>
                <div class="flex-1 min-w-0">
                    <select name="selector_group_id" required class="w-full max-w-md border border-gray-300 rounded px-3 py-2 text-sm">
                        @forelse($selectorGroups as $g)
                        <option value="{{ $g->id }}" {{ (int) old('selector_group_id', $template->selector_group_id) === (int) $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                        @empty
                        <option value="" disabled selected>Every group already has a template — delete one to add another</option>
                        @endforelse
                    </select>
                    @error('selector_group_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Template name</label>
                    <p class="text-xs text-gray-400 mt-0.5">Label in the Emails list.</p>
                </div>
                <div class="flex-1 min-w-0 flex items-center gap-4">
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                           class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm" placeholder="e.g. Adult selectors">
                    <label class="flex items-center gap-2 shrink-0">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" class="w-4 h-4 rounded border-gray-300 text-blue-600"
                               {{ old('enabled', $template->enabled ?? true) ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-gray-700">Enabled</span>
                    </label>
                </div>
            </div>

            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Subject</label>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="text" id="subject-field" name="subject" value="{{ old('subject', $template->subject) }}" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    @if(!empty($subjectTokens))
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="text-xs text-gray-400">Insert:</span>
                        @foreach($subjectTokens as $token)
                        <button type="button" onclick="sfpInsertToken('{{ $token }}', 'subject-field')"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-xs font-mono text-gray-600 border border-gray-200">{{ $token }}</button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Email body</label>
                    <p class="text-xs text-gray-400 mt-0.5">Leave blank to use the default template from <strong>Default staff routing</strong>. Use <code>{action_buttons}</code> for quick-action links.</p>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="hidden" id="body-field" name="body" value="{{ old('body', $template->body) }}">
                    <trix-editor input="body-field" class="trix-content border border-gray-300 rounded bg-white text-sm" style="min-height: 360px;"></trix-editor>
                    @if(!empty($availableTokens))
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="text-xs text-gray-400">Insert:</span>
                        @foreach($availableTokens as $token)
                        <button type="button" onclick="sfpTrixInsert('{{ $token }}', 'body-field')"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-xs font-mono text-gray-600 border border-gray-200">{{ $token }}</button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 flex gap-3">
            @if($selectorGroups->isNotEmpty() || $template->exists)
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
            @endif
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
