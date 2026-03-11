@extends('requests::staff.settings._layout')
@section('title', 'Edit Default Patron Email')
@section('settings-content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.settings.notifications', ['tab' => 'emails']) }}" class="text-sm text-blue-600 hover:underline">&larr; Emails</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Edit default patron email</h1>
</div>

@if(session('success'))
<div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{{ session('success') }}</div>
@endif
@if(session('test_success'))
<div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{{ session('test_success') }}</div>
@endif
@if(session('test_error'))
<div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">{{ session('test_error') }}</div>
@endif

@php
    $subjectTokens = array_values(array_diff($availableTokens ?? [], $subjectExcludedTokens ?? []));
@endphp

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        {{-- Send test (left) + Preview (right) on one line --}}
        <div class="px-5 py-3 flex flex-wrap items-center justify-end gap-3 border-b border-gray-100">
            <form method="POST" action="{{ route('request.staff.settings.notifications.test') }}" class="inline-flex flex-wrap items-center gap-2">
                @csrf
                <input type="hidden" name="type" value="patron">
                <a href="#" onclick="this.closest('form').submit(); return false;" class="text-sm text-blue-600 hover:underline">Send test to</a>
                <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" placeholder="you@example.com"
                       class="border border-gray-300 rounded px-3 py-1.5 text-sm w-52 @error('email') border-red-400 @enderror">
                @error('email')<p class="text-xs text-red-600 w-full">{{ $message }}</p>@enderror
            </form>
            <a href="{{ route('request.staff.settings.notifications.preview', 'patron') }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-700 shrink-0">
                <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                Preview in browser
            </a>
        </div>

    <form method="POST" action="{{ route('request.staff.settings.update') }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="notifications_emails">

        <div class="divide-y divide-gray-100">
            {{-- Enable --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Enable patron status emails</label>
                    <p class="text-xs text-gray-400 mt-0.5">Send an email to the patron when their request's status changes. Only statuses with “Notify” on (in Statuses) will trigger.</p>
                </div>
                <div class="flex-1 min-w-0 flex items-center gap-2">
                    <input type="hidden" name="settings[0][key]" value="patron_status_notification_enabled">
                    <input type="hidden" name="settings[0][value]" value="0">
                    <input type="checkbox" name="settings[0][value]" id="patron-enabled" value="1"
                           {{ old('settings.0.value', $patronEnabled ? '1' : '0') == '1' ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <label for="patron-enabled" class="text-sm font-medium text-gray-700">Enabled</label>
                </div>
            </div>

            {{-- Subject --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Subject</label>
                    <p class="text-xs text-gray-400 mt-0.5">Subject line for this default patron notification.</p>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="hidden" name="settings[1][key]" value="patron_status_subject">
                    <input type="text" id="subject-field" name="settings[1][value]" value="{{ old('settings.1.value', $patronSubjectValue) }}"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    @if(!empty($subjectTokens))
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="text-xs text-gray-400">Insert:</span>
                        @foreach($subjectTokens as $token)
                        <button type="button" onclick="sfpInsertToken('{{ $token }}', 'subject-field')"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">{{ $token }}</button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">Email body</label>
                    <p class="text-xs text-gray-400 mt-0.5">Used when no status-specific patron template matches. Use <code class="text-xs bg-gray-100 px-1 rounded">{status_description}</code> to include the status description.</p>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="hidden" name="settings[2][key]" value="patron_status_template">
                    <input type="hidden" id="body-field" name="settings[2][value]" value="{{ old('settings.2.value', $patronTemplateValue) }}">
                    <trix-editor input="body-field" class="trix-content border border-gray-300 rounded bg-white text-sm" style="min-height: 320px;"></trix-editor>
                    @if(!empty($availableTokens))
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="text-xs text-gray-400">Insert:</span>
                        @foreach($availableTokens as $token)
                        <button type="button" onclick="sfpTrixInsert('{{ $token }}', 'body-field')"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">{{ $token }}</button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Save</button>
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
