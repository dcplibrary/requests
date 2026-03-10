<div class="space-y-3" x-data="{ addOpen: false }">

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-semibold text-gray-700">{{ $title }}</h4>

        <div class="flex items-center gap-3">
            @if($savedMessage)
            <span
                x-data="{ show: true }"
                x-init="setTimeout(() => { show = false; $wire.clearFlash(); }, 2000)"
                x-show="show"
                x-transition:leave="transition duration-500"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="text-xs text-green-600"
            >{{ $savedMessage }}</span>
            @endif

            <button type="button" x-on:click="addOpen = !addOpen"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                + Add
            </button>
        </div>
    </div>

    {{-- ── Add new option (toggled) ── --}}
    <form x-show="addOpen" x-cloak
          wire:submit.prevent="addItem"
          x-on:submit="$nextTick(() => { if (!$wire.newName) addOpen = false })"
          class="flex items-center gap-2">
        <input
            type="text"
            wire:model="newName"
            placeholder="New option name…"
            x-ref="addInput"
            x-effect="if (addOpen) $nextTick(() => $refs.addInput.focus())"
            class="flex-1 rounded-md border border-gray-300 text-sm px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
        />
        <button
            type="submit"
            class="flex items-center gap-1 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-md px-4 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-colors"
        >Add</button>
        <button
            type="button"
            x-on:click="addOpen = false; $wire.set('newName', '')"
            class="text-sm text-gray-500 hover:text-gray-700 px-2 py-1.5"
        >Cancel</button>
    </form>

    {{-- ── Option rows ── --}}
    @if(count($items))
    <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden bg-white">
        @foreach($items as $i => $item)

        @php
            $textExtras = collect($extraFields)->where('type', 'text')
                ->mapWithKeys(fn ($ef) => [$ef['key'] => $item[$ef['key']] ?? ''])
                ->all();
            $boolExtras = collect($extraFields)->where('type', 'boolean')->values()->all();
        @endphp

        <div
            wire:key="opt-{{ $i }}"
            x-data="{
                editOpen: false,
                confirmDelete: false,
                name: @js($item['name']),
                slug: @js($item['slug']),
                extras: @js($textExtras),
                savedExtras: @js($textExtras),
                autoSlug: true,
                reset() {
                    this.name          = @js($item['name']);
                    this.slug          = @js($item['slug']);
                    this.extras        = { ...this.savedExtras };
                    this.autoSlug      = true;
                    this.editOpen      = false;
                    this.confirmDelete = false;
                }
            }"
        >
            {{-- ── Display row ── --}}
            <div class="flex items-center gap-3 px-3 py-2.5 {{ $item['active'] ? '' : 'opacity-60' }}">

                {{-- Sort buttons --}}
                <div class="flex flex-col gap-0.5 shrink-0">
                    <button
                        type="button"
                        wire:click="moveUp({{ $item['id'] }})"
                        wire:loading.attr="disabled"
                        @if($i === 0) disabled @endif
                        class="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-20 disabled:cursor-not-allowed focus:outline-none"
                        title="Move up"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                    </button>
                    <button
                        type="button"
                        wire:click="moveDown({{ $item['id'] }})"
                        wire:loading.attr="disabled"
                        @if($i === count($items) - 1) disabled @endif
                        class="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-20 disabled:cursor-not-allowed focus:outline-none"
                        title="Move down"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>

                {{-- Name + slug + inline annotations --}}
                <div class="flex-1 min-w-0 flex items-baseline gap-2 flex-wrap">
                    <span class="text-sm font-medium text-gray-900">{{ $item['name'] }}</span>
                    <span class="text-xs text-gray-400 font-mono">{{ $item['slug'] }}</span>

                    {{-- Text extras: shown as grey annotations when non-empty --}}
                    @foreach($textExtras as $key => $val)
                    @php $ef = collect($extraFields)->firstWhere('key', $key); @endphp
                    @if($val !== '' && $val !== null)
                    <span class="text-xs text-gray-400 italic">{{ $ef['label'] }}: {{ $val }}</span>
                    @endif
                    @endforeach

                    {{-- Boolean extras: small pill when true, nothing when false --}}
                    @foreach($boolExtras as $ef)
                    @if($item[$ef['key']] ?? false)
                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-600">{{ $ef['label'] }}</span>
                    @endif
                    @endforeach
                </div>

                {{-- Active status pill --}}
                <x-sfp::status-pill :active="$item['active']" />

                {{-- Edit + Delete icons --}}
                <div class="flex items-center gap-0.5 shrink-0">
                    <template x-if="!confirmDelete">
                        <span class="flex items-center gap-0.5">
                            <x-sfp::icon-btn variant="edit"   label="Edit"   x-on:click="editOpen = !editOpen" />
                            <x-sfp::icon-btn variant="delete" label="Delete" x-on:click="confirmDelete = true" />
                        </span>
                    </template>
                    <template x-if="confirmDelete">
                        <span class="flex items-center gap-1.5 text-xs">
                            <span class="text-gray-500">Delete?</span>
                            <button type="button" wire:click="deleteItem({{ $item['id'] }})"
                                class="px-2 py-0.5 rounded bg-red-600 text-white hover:bg-red-700 focus:outline-none">Yes</button>
                            <button type="button" x-on:click="confirmDelete = false"
                                class="px-2 py-0.5 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 focus:outline-none">No</button>
                        </span>
                    </template>
                </div>
            </div>

            {{-- ── Edit panel ── --}}
            <div
                x-show="editOpen"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="border-t border-gray-100 bg-gray-50 px-4 py-4"
            >
                <div class="max-w-lg space-y-3">

                    {{-- Name --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                        <input
                            type="text"
                            x-model="name"
                            x-on:input="if (autoSlug) slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')"
                            class="w-full rounded border border-gray-300 text-sm px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    {{-- Slug — locked or editable --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Slug</label>
                        @if(count($item['locked_by']) > 0)
                        @php $usingLabels = collect($item['locked_by'])->pluck('label')->unique()->join(', '); @endphp
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-mono text-gray-500 px-3 py-1.5 bg-gray-100 rounded border border-gray-200 select-all">{{ $item['slug'] }}</span>
                            <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Used in conditional logic for: <strong class="text-gray-500">{{ $usingLabels }}</strong>
                            </span>
                        </div>
                        @else
                        <input
                            type="text"
                            x-model="slug"
                            x-on:input="autoSlug = false"
                            class="w-full rounded border border-gray-300 text-sm font-mono px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500 text-gray-600"
                        />
                        @endif
                    </div>

                    {{-- Text extra fields --}}
                    @foreach($textExtras as $key => $val)
                    @php $ef = collect($extraFields)->firstWhere('key', $key); @endphp
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ $ef['label'] }}</label>
                        <input
                            type="text"
                            x-model="extras[{{ json_encode($key) }}]"
                            class="w-full rounded border border-gray-300 text-sm px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    @endforeach

                    {{-- Boolean extra fields --}}
                    @foreach($boolExtras as $ef)
                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="bool_{{ $item['id'] }}_{{ $ef['key'] }}"
                            @checked((bool) ($item[$ef['key']] ?? false))
                            x-on:change="$wire.toggleBoolField({{ $item['id'] }}, '{{ $ef['key'] }}')"
                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        />
                        <label for="bool_{{ $item['id'] }}_{{ $ef['key'] }}" class="text-sm font-medium text-gray-700 cursor-pointer">
                            {{ $ef['label'] }}
                        </label>
                    </div>
                    @endforeach

                    {{-- Active --}}
                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="active_{{ $item['id'] }}"
                            @checked($item['active'])
                            x-on:change="$wire.toggleActive({{ $item['id'] }})"
                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                        />
                        <label for="active_{{ $item['id'] }}" class="text-sm font-medium text-gray-700 cursor-pointer">Active</label>
                    </div>

                    {{-- Save / Cancel --}}
                    <div class="flex items-center gap-2 pt-1">
                        <button
                            type="button"
                            x-on:click="$wire.updateItem({{ $item['id'] }}, name, slug, extras); savedExtras = { ...extras }; editOpen = false"
                            class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500"
                        >Save Changes</button>
                        <button
                            type="button"
                            x-on:click="reset()"
                            class="px-4 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-gray-400"
                        >Cancel</button>
                    </div>

                </div>
            </div>

        </div>
        @endforeach
    </div>
    @else
    <p class="text-sm text-gray-400 italic">No options yet. Use the Add button to create one.</p>
    @endif

</div>
