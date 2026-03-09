@extends('sfp::staff.settings._layout')
@section('title', 'Settings')
@section('settings-content')
<form method="POST" action="{{ route('request.staff.settings.update') }}">
    @csrf @method('PATCH')

    @php $i = 0; @endphp

    @forelse($settings as $group => $items)
    @php
        $groupLabel = match($group) {
            'ill'            => 'ILL',
            'request_limits' => 'Request Limits',
            default          => ucwords(str_replace('_', ' ', $group)) ?: 'General',
        };
    @endphp

    @if($group === 'request_limits')
    {{-- ------------------------------------------------------------------ --}}
    {{-- Custom section: conditionally shows window-type-dependent fields.   --}}
    {{-- ------------------------------------------------------------------ --}}
    @php
        $limitCountItem    = $items->firstWhere('key', 'sfp_limit_count');
        $windowTypeItem    = $items->firstWhere('key', 'sfp_limit_window_type');
        $windowDaysItem    = $items->firstWhere('key', 'sfp_limit_window_days');
        $resetDayItem      = $items->firstWhere('key', 'sfp_limit_calendar_reset_day');
        $initialWindowType = $windowTypeItem?->value ?? 'rolling';
    @endphp
    <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden"
         x-data="{ windowType: '{{ $initialWindowType }}' }">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">{{ $groupLabel }}</h2>
        </div>
        <div class="divide-y divide-gray-100">

            {{-- Request Limit Count --}}
            @if($limitCountItem)
            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $limitCountItem->key }}">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">{{ $limitCountItem->label ?? $limitCountItem->key }}</label>
                    @if($limitCountItem->description)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $limitCountItem->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <input type="number"
                               name="settings[{{ $i }}][value]"
                               value="{{ old("settings.{$i}.value", $limitCountItem->value) }}"
                               min="1"
                               class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
                        <span class="text-sm text-gray-500">requests</span>
                    </div>
                </div>
            </div>
            @php $i++; @endphp
            @endif

            {{-- Limit Window Type (radio) --}}
            @if($windowTypeItem)
            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $windowTypeItem->key }}">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">{{ $windowTypeItem->label ?? $windowTypeItem->key }}</label>
                    @if($windowTypeItem->description)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $windowTypeItem->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex flex-col gap-2.5">
                        @foreach(['rolling' => 'Rolling', 'calendar_month' => 'Calendar Month', 'calendar_week' => 'Calendar Week'] as $val => $lbl)
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio"
                                   name="settings[{{ $i }}][value]"
                                   value="{{ $val }}"
                                   x-model="windowType"
                                   class="w-4 h-4 border-gray-300 text-blue-600">
                            {{ $lbl }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            @php $i++; @endphp
            @endif

            {{-- Rolling Window Length — only visible when type is "rolling" --}}
            @if($windowDaysItem)
            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $windowDaysItem->key }}">
            <div class="px-5 py-4 flex items-start gap-6" x-show="windowType === 'rolling'" x-cloak>
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">{{ $windowDaysItem->label ?? $windowDaysItem->key }}</label>
                    @if($windowDaysItem->description)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $windowDaysItem->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <input type="number"
                               name="settings[{{ $i }}][value]"
                               value="{{ old("settings.{$i}.value", $windowDaysItem->value) }}"
                               min="1"
                               class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
                        <span class="text-sm text-gray-500">days</span>
                    </div>
                </div>
            </div>
            @php $i++; @endphp
            @endif

            {{-- Monthly Reset Day — only visible when type is "calendar_month" --}}
            @if($resetDayItem)
            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $resetDayItem->key }}">
            <div class="px-5 py-4 flex items-start gap-6" x-show="windowType === 'calendar_month'" x-cloak>
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">{{ $resetDayItem->label ?? $resetDayItem->key }}</label>
                    @if($resetDayItem->description)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $resetDayItem->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <input type="number"
                               name="settings[{{ $i }}][value]"
                               value="{{ old("settings.{$i}.value", $resetDayItem->value) }}"
                               min="1" max="28"
                               class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
                        <span class="text-sm text-gray-500">of each month</span>
                    </div>
                </div>
            </div>
            @php $i++; @endphp
            @endif

        </div>
    </div>

    @else
    {{-- ------------------------------------------------------------------ --}}
    {{-- Generic section for all other groups.                               --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">{{ $groupLabel }}</h2>
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
                    $fieldId = 'setting-field-' . $i;
                    $trixId  = 'trix-' . $i;
                    $tokens  = $setting->tokens ?? [];
                @endphp
                <div class="flex-1">
                    @if($setting->type === 'boolean')
                        <input type="hidden" name="settings[{{ $i }}][value]" value="0">
                        <input type="checkbox"
                               id="{{ $fieldId }}"
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
                                     class="trix-content border border-gray-300 rounded bg-white text-sm min-h-[8rem]"></trix-editor>
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

                    @elseif($setting->type === 'integer')
                        @php $isDays = str_ends_with($setting->key, '_days'); @endphp
                        <div class="flex items-center gap-2">
                            <input type="number"
                                   id="{{ $fieldId }}"
                                   name="settings[{{ $i }}][value]"
                                   value="{{ old("settings.{$i}.value", $setting->value) }}"
                                   min="0"
                                   class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
                            @if($isDays)
                                <span class="text-sm text-gray-500">days</span>
                            @endif
                        </div>

                    @else
                        {{-- string / default type --}}
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
    @endif

    @empty
    <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-400">
        No settings found. Run the seeders to populate default settings.
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
