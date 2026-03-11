<div>
    {{-- Tabs --}}
    <div class="bg-white rounded-lg border border-gray-200 mb-4 overflow-hidden">
        <nav class="flex border-b border-gray-200" role="tablist" aria-label="Form type">
            <button type="button"
                    role="tab"
                    wire:click="$set('activeFormTab', 'sfp')"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors
                           {{ $activeFormTab === 'sfp' ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50' }}">
                Suggest for Purchase
            </button>
            <button type="button"
                    role="tab"
                    wire:click="$set('activeFormTab', 'ill')"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors
                           {{ $activeFormTab === 'ill' ? 'bg-blue-50 text-blue-700 font-semibold border-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50' }}">
                Interlibrary Loan
            </button>
        </nav>
    </div>

    {{-- Suggest for Purchase panel --}}
    @if($activeFormTab === 'sfp')
    <div class="mb-6">
        <div class="flex items-center justify-end mb-3">
            <a href="{{ route('request.staff.settings.form-fields.create', ['form' => 'sfp']) }}"
               class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Field</a>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 w-16">Order</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Label</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Key</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Token</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Condition</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Required</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($suggestFields as $index => $field)
                    <tr wire:key="suggest-{{ $index }}" class="hover:bg-gray-50 {{ $field['active'] ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3">
                            <x-requests::sort-btns :value="$index" up="moveUpSuggest" down="moveDownSuggest"
                                :first="$index === 0" :last="$index === count($suggestFields) - 1" size="md" />
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $field['label'] }}</td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $field['key'] }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $field['type'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 rounded px-1.5 py-0.5 font-mono">{{ '{' . $field['key'] . '}' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($field['has_condition'] && ! empty($field['condition']['rules']))
                                <span class="inline-flex items-center gap-1 text-xs text-indigo-600 bg-indigo-50 rounded px-1.5 py-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                                    conditional
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-requests::status-pill :active="$field['required']" active-label="Required" :show-inactive="false" />
                        </td>
                        <td class="px-4 py-3">
                            <x-requests::status-pill :active="$field['active']" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-requests::icon-btn :href="$field['edit_url']" variant="edit" label="Edit" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-gray-400">No fields found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Interlibrary Loan panel --}}
    @if($activeFormTab === 'ill')
    <div class="mb-6">
        <div class="flex items-center justify-end mb-3">
            <a href="{{ route('request.staff.settings.form-fields.create', ['form' => 'ill']) }}"
               class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">+ New Field</a>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 w-16">Order</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Label</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Key</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Token</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Condition</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Required</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($illFields as $index => $field)
                    <tr wire:key="ill-{{ $index }}" class="hover:bg-gray-50 {{ $field['active'] ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3">
                            <x-requests::sort-btns :value="$index" up="moveUpIll" down="moveDownIll"
                                :first="$index === 0" :last="$index === count($illFields) - 1" size="md" />
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $field['label'] }}</td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $field['key'] }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $field['type'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 rounded px-1.5 py-0.5 font-mono">{{ '{' . $field['key'] . '}' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($field['has_condition'] && ! empty($field['condition']['rules']))
                                <span class="inline-flex items-center gap-1 text-xs text-indigo-600 bg-indigo-50 rounded px-1.5 py-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                                    conditional
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-requests::status-pill :active="$field['required']" active-label="Required" :show-inactive="false" />
                        </td>
                        <td class="px-4 py-3">
                            <x-requests::status-pill :active="$field['active']" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-requests::icon-btn :href="$field['edit_url']" variant="edit" label="Edit" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-gray-400">No fields found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
