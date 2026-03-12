<div class="space-y-6">

    {{-- ── Basic settings ── --}}
    <div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
        <div class="space-y-4">

            <div>
                <label for="ff_label" class="block text-sm font-medium text-gray-700 mb-1">
                    Label <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="ff_label"
                    wire:model="label"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                />
                @error('label')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-400">Shown to staff in the admin. Does not affect field behaviour.</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="required" value="0">
                <input
                    type="checkbox"
                    id="ff_required"
                    wire:model="required"
                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <label for="ff_required" class="text-sm font-medium text-gray-700 cursor-pointer">Required</label>
                <span class="text-xs text-gray-400">— patron must complete this field before submitting</span>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input
                    type="checkbox"
                    id="ff_active"
                    wire:model="active"
                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <label for="ff_active" class="text-sm font-medium text-gray-700 cursor-pointer">Active</label>
                <span class="text-xs text-gray-400">— show this field on the patron form</span>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="includeAsToken" value="0">
                <input
                    type="checkbox"
                    id="ff_token"
                    wire:model="includeAsToken"
                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <label for="ff_token" class="text-sm font-medium text-gray-700 cursor-pointer">Include as token</label>
                <span class="text-xs text-gray-400">— makes <span class="font-mono text-gray-500">{<span class="text-gray-500">{{ $fieldKey }}</span>}</span> available in notification templates</span>
            </div>

            @if($showOptionsManager)
            <div class="flex items-center gap-2">
                <input type="hidden" name="filterable" value="0">
                <input
                    type="checkbox"
                    id="ff_filterable"
                    wire:model="filterable"
                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <label for="ff_filterable" class="text-sm font-medium text-gray-700 cursor-pointer">Filterable</label>
                <span class="text-xs text-gray-400">— enables staff list filtering and selector group scoping by this field's options</span>
            </div>
            @endif

        </div>
    </div>

    {{-- ── Conditional logic ── --}}
    <div class="max-w-2xl bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Conditional Logic</h2>

        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 mb-4">
            <input
                type="checkbox"
                wire:click="toggleHasCondition"
                @checked($hasCondition)
                class="rounded text-indigo-600 focus:ring-indigo-500"
            />
            Only show this field when conditions are met
        </label>

        @if($hasCondition)
        <div class="pl-6 space-y-3">

            <div class="flex items-center gap-2 text-sm text-gray-600">
                Show when
                <select
                    wire:change="setConditionMatch($event.target.value)"
                    class="rounded border border-gray-300 text-sm px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="all" @selected(($condition['match'] ?? 'all') === 'all')>ALL</option>
                    <option value="any" @selected(($condition['match'] ?? 'all') === 'any')>ANY</option>
                </select>
                of the following conditions are met:
            </div>

            @foreach($condition['rules'] as $ruleIndex => $rule)
            <div class="flex items-start gap-2 flex-wrap" wire:key="rule-{{ $ruleIndex }}">

                <select
                    wire:change="setRuleField({{ $ruleIndex }}, $event.target.value)"
                    class="rounded border border-gray-300 text-sm px-2 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="material_type" @selected(($rule['field'] ?? '') === 'material_type')>Material Type</option>
                    <option value="audience"      @selected(($rule['field'] ?? '') === 'audience')>Audience</option>
                </select>

                <select
                    wire:change="setRuleOperator({{ $ruleIndex }}, $event.target.value)"
                    class="rounded border border-gray-300 text-sm px-2 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="in"     @selected(($rule['operator'] ?? 'in') === 'in')>is any of</option>
                    <option value="not_in" @selected(($rule['operator'] ?? 'in') === 'not_in')>is none of</option>
                </select>

                <div class="flex flex-wrap gap-x-3 gap-y-1.5 items-center">
                    @php
                        $options = ($rule['field'] ?? 'material_type') === 'audience'
                            ? $audienceOptions
                            : $materialTypeOptions;
                    @endphp
                    @foreach($options as $slug => $name)
                    <label class="flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:click="toggleRuleValue({{ $ruleIndex }}, '{{ $slug }}')"
                            @checked(in_array($slug, $rule['values'] ?? []))
                            class="rounded text-indigo-600 focus:ring-indigo-500"
                        />
                        {{ $name }}
                    </label>
                    @endforeach
                </div>

                <button
                    type="button"
                    wire:click="removeRule({{ $ruleIndex }})"
                    class="text-red-400 hover:text-red-600 focus:outline-none mt-1"
                    title="Remove rule"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @endforeach

            <button
                type="button"
                wire:click="addRule"
                class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 focus:outline-none"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add condition
            </button>

        </div>
        @endif
    </div>

    {{-- ── Options hint (managed per-form) ── --}}
    @if($showOptionsManager)
    <div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Options</h2>
        <p class="text-sm text-gray-500">Options for this field are managed per-form. Edit this field from the <a href="{{ route('request.staff.settings.form-fields') }}" class="text-blue-600 hover:underline">SFP or ILL form tab</a> to manage option visibility and order.</p>
    </div>
    @endif

    {{-- ── Save / Cancel ── --}}
    <div class="flex items-center gap-3">
        <button
            type="button"
            wire:click="save"
            class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
        >Save Changes</button>
        <a
            href="{{ route('request.staff.settings.form-fields') }}"
            class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200"
        >Cancel</a>
    </div>

</div>
