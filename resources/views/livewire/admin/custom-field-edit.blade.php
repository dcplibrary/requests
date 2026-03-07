<div class="space-y-6">

    <div class="max-w-2xl bg-white rounded-lg border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Label <span class="text-red-500">*</span></label>
                <input type="text" wire:model="label"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" />
                @error('label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Key <span class="text-red-500">*</span></label>
                <input type="text" wire:model="key"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500" />
                @error('key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-400">Used as the token name: <span class="font-mono">{<span>{{ $key }}</span>}</span></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select wire:model="type" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    @foreach(['text','textarea','date','number','checkbox','select','radio'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Step</label>
                <input type="number" min="1" max="10" wire:model="step"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Applies to</label>
                <select wire:model="requestKind" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    <option value="sfp">sfp</option>
                    <option value="ill">ill</option>
                    <option value="both">both</option>
                </select>
            </div>

            <div class="md:col-span-2 space-y-2 pt-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="required" class="rounded text-blue-600 focus:ring-blue-500" />
                    Required
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="active" class="rounded text-blue-600 focus:ring-blue-500" />
                    Active
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="includeAsToken" class="rounded text-blue-600 focus:ring-blue-500" />
                    Include as token
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="filterable" class="rounded text-blue-600 focus:ring-blue-500" />
                    Filterable (staff list)
                </label>
            </div>
        </div>
    </div>

    {{-- Conditional logic --}}
    <div class="max-w-3xl bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Conditional Logic</h2>

        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 mb-4">
            <input type="checkbox" wire:click="toggleHasCondition" @checked($hasCondition)
                   class="rounded text-indigo-600 focus:ring-indigo-500" />
            Only show this field when conditions are met
        </label>

        @if($hasCondition)
        <div class="pl-6 space-y-3">
            <div class="flex items-center gap-2 text-sm text-gray-600">
                Show when
                <select wire:change="setConditionMatch($event.target.value)"
                        class="rounded border border-gray-300 text-sm px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all" @selected(($condition['match'] ?? 'all') === 'all')>ALL</option>
                    <option value="any" @selected(($condition['match'] ?? 'all') === 'any')>ANY</option>
                </select>
                of the following conditions are met:
            </div>

            @foreach($condition['rules'] as $ruleIndex => $rule)
            <div class="flex items-start gap-2 flex-wrap" wire:key="cfr-{{ $ruleIndex }}">
                <select wire:change="setRuleField({{ $ruleIndex }}, $event.target.value)"
                        class="rounded border border-gray-300 text-sm px-2 py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach($conditionFieldKeys as $k)
                        <option value="{{ $k }}" @selected(($rule['field'] ?? '') === $k)>{{ $k }}</option>
                    @endforeach
                </select>

                <select wire:change="setRuleOperator({{ $ruleIndex }}, $event.target.value)"
                        class="rounded border border-gray-300 text-sm px-2 py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="in"     @selected(($rule['operator'] ?? 'in') === 'in')>is any of</option>
                    <option value="not_in" @selected(($rule['operator'] ?? 'in') === 'not_in')>is none of</option>
                </select>

                <div class="flex flex-wrap gap-x-3 gap-y-1.5 items-center">
                    @php
                        $ruleKey = $rule['field'] ?? '';
                        $opts = $optionsByKey[$ruleKey] ?? [];
                    @endphp
                    @forelse($opts as $slug => $name)
                    <label class="flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                        <input type="checkbox"
                               wire:click="toggleRuleValue({{ $ruleIndex }}, '{{ $slug }}')"
                               @checked(in_array($slug, $rule['values'] ?? []))
                               class="rounded text-indigo-600 focus:ring-indigo-500" />
                        {{ $name }}
                    </label>
                    @empty
                    <span class="text-xs text-gray-400 italic">No options for this controller yet.</span>
                    @endforelse
                </div>

                <button type="button" wire:click="removeRule({{ $ruleIndex }})"
                        class="text-red-400 hover:text-red-600 focus:outline-none mt-1" title="Remove rule">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @endforeach

            <button type="button" wire:click="addRule"
                    class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 focus:outline-none">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add condition
            </button>
        </div>
        @endif
    </div>

    {{-- Options manager --}}
    @if($showOptionsManager)
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Options</h2>
        @livewire('sfp-admin-custom-field-options', ['fieldId' => $field->id], key('cfo-' . $field->id))
    </div>
    @endif

    <div class="flex items-center gap-3">
        <button type="button" wire:click="save"
                class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
            Save Changes
        </button>
        <a href="{{ route('sfp.staff.settings.custom-fields') }}"
           class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">
            Cancel
        </a>
    </div>
</div>

