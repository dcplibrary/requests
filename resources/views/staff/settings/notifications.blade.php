@extends('sfp::staff.settings._layout')
@section('title', 'Notification Settings')
@section('settings-content')

<form method="POST" action="{{ route('sfp.staff.settings.update') }}">
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
                    $tokens = in_array($setting->type, ['string', 'text', 'html'])
                        ? ($availableTokens ?? [])
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
