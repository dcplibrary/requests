@extends('sfp::staff.settings._layout')
@section('title', 'Notification Settings')
@section('settings-content')

<form method="POST" action="{{ route('request.staff.settings.update') }}">
    @csrf @method('PATCH')

    @php $i = 0; @endphp

    @forelse($settings as $group => $items)
    <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700 capitalize">{{ $group ?: 'Notifications' }}</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($items as $setting)
            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $setting->key }}">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">{{ $setting->label ?? $setting->key }}</label>
                    @if($setting->description)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $setting->description }}</p>
                    @endif
                </div>
                @php
                    $fieldId = 'notif-field-' . $i;
                    $trixId  = 'trix-notif-' . $i;
                    // Tokens apply to text/string/html settings only (not boolean toggles).
                    $tokenList = $availableTokens ?? $setting->tokens ?? [];
                    $tokens = in_array($setting->type, ['string', 'text', 'html'])
                        ? (is_array($tokenList) ? $tokenList : [])
                        : [];
                @endphp
                <div class="flex-1">
                    @if($setting->type === 'boolean')
                        <input type="hidden" name="settings[{{ $i }}][value]" value="0">
                        <input type="checkbox"
                               name="settings[{{ $i }}][value]"
                               value="1"
                               {{ $setting->value ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-gray-300 text-blue-600">

                    @elseif($setting->type === 'html')
                        <input type="hidden"
                               id="{{ $trixId }}"
                               name="settings[{{ $i }}][value]"
                               value="{{ $setting->value ?? '' }}">
                        <trix-editor input="{{ $trixId }}"
                                     class="trix-content border border-gray-300 rounded bg-white text-sm min-h-[10rem]"></trix-editor>
                        @if(count($tokens))
                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                            <span class="text-xs text-gray-400">Insert:</span>
                            @foreach($tokens as $token)
                            <button type="button"
                                    onclick="sfpTrixInsert('{{ $token }}', '{{ $trixId }}')"
                                    class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">
                                {{ $token }}
                            </button>
                            @endforeach
                        </div>
                        @endif

                    @elseif($setting->type === 'text' || $setting->type === 'textarea')
                        <textarea id="{{ $fieldId }}"
                                  name="settings[{{ $i }}][value]"
                                  rows="3"
                                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm resize-y">{{ $setting->value }}</textarea>
                        @if(count($tokens))
                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                            <span class="text-xs text-gray-400">Insert:</span>
                            @foreach($tokens as $token)
                            <button type="button"
                                    onclick="sfpInsertToken('{{ $token }}', '{{ $fieldId }}')"
                                    class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">
                                {{ $token }}
                            </button>
                            @endforeach
                        </div>
                        @endif

                    @else
                        {{-- string / default type (e.g. subject lines) --}}
                        <input type="text"
                               id="{{ $fieldId }}"
                               name="settings[{{ $i }}][value]"
                               value="{{ old("settings.{$i}.value", $setting->value) }}"
                               class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        @if(count($tokens))
                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                            <span class="text-xs text-gray-400">Insert:</span>
                            @foreach($tokens as $token)
                            <button type="button"
                                    onclick="sfpInsertToken('{{ $token }}', '{{ $fieldId }}')"
                                    class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">
                                {{ $token }}
                            </button>
                            @endforeach
                        </div>
                        @endif
                    @endif
                </div>
            </div>
            @php $i++; @endphp
            @endforeach
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-400">
        No notification settings found. Run the migrations to populate them.
    </div>
    @endforelse

    @if($settings->isNotEmpty())
    <div class="flex justify-end">
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            Save Settings
        </button>
    </div>
    @endif
</form>

{{-- ── Preview & Test ── --}}
<div class="bg-white rounded-lg border border-gray-200 mt-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
        <h2 class="text-sm font-semibold text-gray-700">Preview & Test Emails</h2>
        <p class="text-xs text-gray-400 mt-0.5">Uses sample data to render the current saved templates.</p>
    </div>

    <div class="px-5 py-4 space-y-4">

        @if(session('test_success'))
        <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
            {{ session('test_success') }}
        </div>
        @endif
        @if(session('test_error'))
        <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
            {{ session('test_error') }}
        </div>
        @endif

        {{-- Preview links --}}
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-600 w-32 shrink-0">Preview in browser</span>
            <a href="{{ route('request.staff.settings.notifications.preview', 'staff') }}" target="_blank"
               class="inline-flex items-center gap-1 px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                Staff Routing
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
            </a>
            <a href="{{ route('request.staff.settings.notifications.preview', 'patron') }}" target="_blank"
               class="inline-flex items-center gap-1 px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                Patron Status
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
            </a>
        </div>

        {{-- Send test --}}
        <form method="POST" action="{{ route('request.staff.settings.notifications.test') }}"
              class="flex items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Send test to</label>
                <input type="email" name="email"
                       placeholder="you@example.com"
                       value="{{ old('email', auth()->user()?->email) }}"
                       class="border border-gray-300 rounded px-3 py-1.5 text-sm w-56 @error('email') border-red-400 @enderror">
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Template</label>
                <select name="type" class="border border-gray-300 rounded px-3 py-1.5 text-sm bg-white">
                    <option value="staff">Staff Routing</option>
                    <option value="patron">Patron Status</option>
                </select>
            </div>
            <button type="submit"
                    class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                Send Test
            </button>
        </form>

    </div>
</div>

<script>
function sfpTrixInsert(token, inputId) {
    var trix = document.querySelector('trix-editor[input="' + inputId + '"]');
    if (trix && trix.editor) { trix.editor.insertString(token); trix.focus(); }
}
function sfpInsertToken(token, id) {
    var el = document.getElementById(id);
    if (!el) return;
    var s = el.selectionStart ?? el.value.length, e = el.selectionEnd ?? el.value.length;
    el.value = el.value.slice(0, s) + token + el.value.slice(e);
    el.selectionStart = el.selectionEnd = s + token.length;
    el.focus();
}
</script>
@endsection
