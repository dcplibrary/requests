@extends('sfp::staff.settings._layout')
@section('title', 'Notification Settings')
@section('settings-content')

@if(session('test_success'))
<div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
    {{ session('test_success') }}
</div>
@endif
@if(session('test_error'))
<div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
    {{ session('test_error') }}
</div>
@endif

<form method="POST" action="{{ route('request.staff.settings.update') }}" id="notifications-form">
    @csrf @method('PATCH')

    {{-- Tab bar (same visual language as settings sidebar) --}}
    <div class="bg-white rounded-lg border border-gray-200 mb-4 overflow-hidden">
        <nav class="flex border-b border-gray-200" role="tablist" aria-label="Notification sections">
            @foreach(['general' => 'General', 'emails' => 'Emails'] as $tabId => $tabLabel)
                <button type="button"
                        role="tab"
                        aria-selected="{{ (request()->get('tab', 'general') === $tabId || (request()->get('tab') === null && $tabId === 'general')) ? 'true' : 'false' }}"
                        aria-controls="notif-panel-{{ $tabId }}"
                        id="notif-tab-{{ $tabId }}"
                        data-notifications-tab="{{ $tabId }}"
                        class="notifications-tab px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors
                               {{ request()->get('tab', 'general') === $tabId || (request()->get('tab') === null && $tabId === 'general')
                                  ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600'
                                  : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    @php $i = 0; @endphp
    @foreach(['general', 'emails'] as $tabId)
        @php
            $items = $settingsByTab[$tabId] ?? collect();
            $panelId = 'notif-panel-' . $tabId;
            $isActive = request()->get('tab', 'general') === $tabId || (request()->get('tab') === null && $tabId === 'general');
        @endphp
        <div class="notifications-panel bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden {{ $isActive ? '' : 'hidden' }}"
             id="{{ $panelId }}"
             role="tabpanel"
             aria-labelledby="notif-tab-{{ $tabId }}"
             @if($tabId === 'general')
             x-data="{ previewEnabled: {{ (int)(bool)($settingsByTab['general'] ?? collect())->firstWhere('key','email_preview_enabled')?->value }} }"
             @endif>
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">
                    {{ $tabId === 'general' ? 'General' : 'Emails' }}
                </h2>
                @if($tabId === 'general')
                    <p class="text-xs text-gray-400 mt-0.5">Master switch and footer used for all notification emails.</p>
                @else
                    <p class="text-xs text-gray-400 mt-0.5">Staff and patron email templates. Edit each row or add patron templates.</p>
                @endif
            </div>
            @if($items->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach($items as $setting)
                    <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $setting->key }}">
                    @php
                        $isEnableRow = ($tabId === 'emails' && in_array($setting->key, ['staff_routing_enabled', 'patron_status_notification_enabled']));
                    @endphp
                    @php
                        $fieldId = 'notif-field-' . $i;
                        $trixId  = 'trix-notif-' . $i;
                        $tokenList = in_array($setting->key, ['staff_routing_subject', 'patron_status_subject'])
                            ? array_values(array_diff($availableTokens ?? [], $subjectExcludedTokens ?? []))
                            : ($availableTokens ?? $setting->tokens ?? []);
                        $tokens = in_array($setting->type, ['string', 'text', 'html'])
                            ? (is_array($tokenList) ? $tokenList : [])
                            : [];
                        $isEmailBody = $setting->type === 'html' && in_array($setting->key, ['staff_routing_template', 'patron_status_template']);
                    @endphp
                    <div class="px-5 py-4 flex items-start gap-6">
                        <div class="w-64 flex-shrink-0">
                            <label class="block text-sm font-medium text-gray-800">{{ $setting->label ?? $setting->key }}</label>
                            @if($setting->description)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $setting->description }}</p>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0 flex items-start justify-between gap-4">
                            <div>
                            @if($setting->type === 'boolean' && $setting->key === 'email_preview_enabled')
                                {{-- Preview toggle: drives the Alpine previewEnabled state --}}
                                <input type="hidden" name="settings[{{ $i }}][value]" value="0">
                                <input type="checkbox"
                                       name="settings[{{ $i }}][value]"
                                       value="1"
                                       {{ $setting->value ? 'checked' : '' }}
                                       @change="previewEnabled = $event.target.checked"
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600">

                            @elseif($setting->type === 'boolean' && $setting->key === 'email_editing_enabled')
                                {{-- Editing toggle: only active when preview is on --}}
                                <div :class="previewEnabled ? '' : 'opacity-40 pointer-events-none'"
                                     class="flex items-center gap-2">
                                    <input type="hidden" name="settings[{{ $i }}][value]" value="0">
                                    <input type="checkbox"
                                           name="settings[{{ $i }}][value]"
                                           value="1"
                                           {{ $setting->value ? 'checked' : '' }}
                                           :disabled="!previewEnabled"
                                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                                    <span x-show="!previewEnabled" class="text-xs text-gray-400 italic">Requires Preview enabled</span>
                                </div>

                            @elseif($setting->type === 'boolean')
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
                                             class="trix-content border border-gray-300 rounded bg-white text-sm {{ $isEmailBody ? '' : 'min-h-[10rem]' }}"
                                             @if($isEmailBody) style="min-height: 480px; height: 480px;" @endif></trix-editor>
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
                            @if($isEnableRow)
                            <div class="flex flex-col items-end gap-2 shrink-0">
                                <a href="{{ route('request.staff.settings.notifications.preview', $tabId) }}"
                                   class="whitespace-nowrap inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 text-gray-700"
                                   onclick="try { window.open(this.href, 'sfpNotifPreview', 'width=680,height=620,scrollbars=yes'); return false; } catch (e) { return true; }">
                                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                    </svg>
                                    Preview in browser
                                </a>
                                <form method="POST" action="{{ route('request.staff.settings.notifications.test') }}" class="flex flex-col items-end gap-1">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $tabId }}">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-600">Send test to</span>
                                        <input type="email" name="email"
                                               placeholder="you@example.com"
                                               value="{{ old('email', auth()->user()?->email) }}"
                                               class="border border-gray-300 rounded px-3 py-1.5 text-sm w-52 @error('email') border-red-400 @enderror">
                                        <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 whitespace-nowrap">
                                            Send Test
                                        </button>
                                    </div>
                                    @error('email')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                </form>
                            </div>
                            @endif
                        </div>
                    </div>
                    @php $i++; @endphp
                @endforeach
            </div>
            @endif

            @if($tabId === 'emails')
            <div class="px-5 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-800">Email templates</h3>
                    <a href="{{ route('request.staff.patron-status-templates.create') }}" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ Add template</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Name</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Type</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Subject</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Triggered by</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Material types</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Default</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-700">Enabled</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-4 py-2 text-gray-900">{{ $staffTitle ?? 'Staff routing' }}</td>
                                <td class="px-4 py-2 text-gray-600">Staff routing</td>
                                <td class="px-4 py-2 text-gray-600">{{ $staffSubject->value ?? '' }}</td>
                                <td class="px-4 py-2 text-gray-600">New request</td>
                                <td class="px-4 py-2 text-gray-400">—</td>
                                <td class="px-4 py-2 text-gray-400">—</td>
                                <td class="px-4 py-2">{{ $staffEnabled ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <x-sfp::icon-btn :href="route('request.staff.settings.notifications.staff-email')" variant="edit" label="Edit" />
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-gray-900">Default patron</td>
                                <td class="px-4 py-2 text-gray-600">Default patron</td>
                                <td class="px-4 py-2 text-gray-600">{{ $patronSubject->value ?? '' }}</td>
                                <td class="px-4 py-2 text-gray-400">Fallback when no status template matches</td>
                                <td class="px-4 py-2 text-gray-400">—</td>
                                <td class="px-4 py-2 text-gray-400">—</td>
                                <td class="px-4 py-2">{{ $patronEnabled ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <x-sfp::icon-btn :href="route('request.staff.settings.notifications.default-patron-email')" variant="edit" label="Edit" />
                                </td>
                            </tr>
                            @foreach($patronStatusTemplates ?? [] as $tpl)
                            <tr>
                                <td class="px-4 py-2 text-gray-900">{{ $tpl->name }}</td>
                                <td class="px-4 py-2 text-gray-600">Patron status</td>
                                <td class="px-4 py-2 text-gray-600">{{ Str::limit($tpl->subject, 40) }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $tpl->requestStatuses->pluck('name')->join(', ') ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $tpl->materialTypes->isNotEmpty() ? $tpl->materialTypes->pluck('name')->join(', ') : 'All' }}</td>
                                <td class="px-4 py-2">{{ $tpl->is_default ? 'Yes' : '—' }}</td>
                                <td class="px-4 py-2">{{ $tpl->enabled ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-2 text-right flex items-center justify-end gap-1">
                                    <x-sfp::icon-btn :href="route('request.staff.patron-status-templates.edit', $tpl)" variant="edit" label="Edit" />
                                    <x-sfp::icon-btn :href="route('request.staff.patron-status-templates.delete', $tpl)" variant="delete" label="Delete" />
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    @endforeach

    @if($notificationSettings->isNotEmpty())
    @php $saveForGeneralOnly = request()->get('tab', 'general') === 'general'; @endphp
    <div id="notifications-save-row" class="flex justify-end {{ $saveForGeneralOnly ? '' : 'hidden' }}">
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            Save Settings
        </button>
    </div>
    @endif
</form>

<script>
(function() {
    var tabs = document.querySelectorAll('.notifications-tab');
    var panels = document.querySelectorAll('.notifications-panel');
    function switchTo(tabId) {
        var q = 'tab=' + tabId;
        if (window.history.replaceState) {
            var u = new URL(window.location);
            u.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', u);
        }
        tabs.forEach(function(t) {
            var isActive = t.getAttribute('data-notifications-tab') === tabId;
            t.setAttribute('aria-selected', isActive ? 'true' : 'false');
            t.classList.toggle('bg-blue-50', isActive);
            t.classList.toggle('text-blue-700', isActive);
            t.classList.toggle('font-semibold', isActive);
            t.classList.toggle('border-blue-600', isActive);
            t.classList.toggle('border-transparent', !isActive);
            t.classList.toggle('text-gray-600', !isActive);
        });
        panels.forEach(function(p) {
            p.classList.toggle('hidden', p.id !== 'notif-panel-' + tabId);
        });
        var saveRow = document.getElementById('notifications-save-row');
        if (saveRow) saveRow.classList.toggle('hidden', tabId !== 'general');
    }
    tabs.forEach(function(t) {
        t.addEventListener('click', function() {
            switchTo(t.getAttribute('data-notifications-tab'));
        });
    });
    var initial = new URLSearchParams(window.location.search).get('tab') || 'general';
    if (['general','emails'].indexOf(initial) !== -1) {
        switchTo(initial);
    }
})();
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
