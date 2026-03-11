<div>
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 w-16">Order</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Label</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Key</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Step</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Kind</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Token</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Filter</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Condition</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Required</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($fields as $index => $field)
                <tr wire:key="cf-{{ $index }}" class="hover:bg-gray-50 {{ $field['active'] ? '' : 'opacity-60' }}">
                    <td class="px-4 py-3">
                        <x-sfp::sort-btns :value="$index" :first="$index === 0" :last="$index === count($fields) - 1" size="md" />
                    </td>

                    <td class="px-4 py-3 font-medium text-gray-900">{{ $field['label'] }}</td>
                    <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $field['key'] }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $field['type'] }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $field['step'] }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ match($field['request_kind'] ?? '') { 'sfp' => 'Suggest for Purchase', 'ill' => 'Interlibrary Loan', 'both' => 'Both', default => $field['request_kind'] ?? '—' } }}</td>

                    <td class="px-4 py-3">
                        @if($field['include_as_token'])
                            <span class="inline-flex items-center text-xs text-emerald-700 bg-emerald-50 rounded px-1.5 py-0.5 font-mono">
                                {{ '{' . $field['key'] . '}' }}
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>

                    <td class="px-4 py-3">
                        @if($field['filterable'])
                            <span class="inline-flex items-center text-xs text-blue-700 bg-blue-50 rounded px-1.5 py-0.5">filter</span>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>

                    <td class="px-4 py-3">
                        @if($field['has_condition'])
                            <span class="inline-flex items-center gap-1 text-xs text-indigo-600 bg-indigo-50 rounded px-1.5 py-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                                conditional
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>

                    <td class="px-4 py-3">
                        <x-sfp::status-pill :active="$field['required']" active-label="Required" :show-inactive="false" />
                    </td>

                    <td class="px-4 py-3">
                        <x-sfp::status-pill :active="$field['active']" />
                    </td>

                    <td class="px-4 py-3 text-right">
                        <x-sfp::icon-btn
                            :href="route('request.staff.settings.custom-fields.edit', ['field' => $field['id']])"
                            variant="edit"
                            label="Edit"
                        />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="px-4 py-10 text-center text-gray-400">No custom fields found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

