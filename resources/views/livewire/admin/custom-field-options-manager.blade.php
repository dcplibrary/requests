<div class="space-y-3" x-data="{ addOpen: false }">
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-semibold text-gray-700">{{ $field->label }} Options</h4>

        <button type="button" x-on:click="addOpen = !addOpen"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            + Add
        </button>
    </div>

    <form x-show="addOpen" x-cloak wire:submit.prevent="addItem"
          class="flex items-center gap-2">
        <input type="text" wire:model="newName" placeholder="New option name…"
               class="flex-1 rounded-md border border-gray-300 text-sm px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />
        <button type="submit" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-md px-4 py-1.5">Add</button>
        <button type="button" x-on:click="addOpen = false; $wire.set('newName', '')"
                class="text-sm text-gray-500 hover:text-gray-700 px-2 py-1.5">Cancel</button>
    </form>

    @if(count($items))
    <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden bg-white">
        @foreach($items as $i => $item)
        <div wire:key="cfo-{{ $i }}"
             x-data="{
                editOpen: false,
                confirmDelete: false,
                name: @js($item['name']),
                slug: @js($item['slug']),
                autoSlug: true,
                reset() { this.name=@js($item['name']); this.slug=@js($item['slug']); this.autoSlug=true; this.editOpen=false; this.confirmDelete=false; }
             }"
        >
            <div class="flex items-center gap-3 px-3 py-2.5 {{ $item['active'] ? '' : 'opacity-60' }}">
                <x-dcpl::sort-btns :value="$item['id']" :first="$i === 0" :last="$i === count($items) - 1" />

                <div class="flex-1 min-w-0 flex items-baseline gap-2 flex-wrap">
                    <span class="text-sm font-medium text-gray-900">{{ $item['name'] }}</span>
                    <span class="text-xs text-gray-400 font-mono">{{ $item['slug'] }}</span>
                    @if(count($item['locked_by']) > 0)
                        @php $using = collect($item['locked_by'])->pluck('label')->unique()->join(', '); @endphp
                        <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Used by: <strong class="text-gray-500">{{ $using }}</strong>
                        </span>
                    @endif
                </div>

                <x-dcpl::status-pill :active="$item['active']" />

                <div class="flex items-center gap-0.5 shrink-0">
                    <template x-if="!confirmDelete">
                        <span class="flex items-center gap-0.5">
                            <x-dcpl::icon-btn variant="edit" label="Edit" x-on:click="editOpen = !editOpen" />
                            <x-dcpl::icon-btn variant="delete" label="Delete" x-on:click="confirmDelete = true" />
                        </span>
                    </template>
                    <template x-if="confirmDelete">
                        <span class="flex items-center gap-1.5 text-xs">
                            <span class="text-gray-500">Delete?</span>
                            <button type="button" wire:click="deleteItem({{ $item['id'] }})"
                                    class="px-2 py-0.5 rounded bg-red-600 text-white hover:bg-red-700">Yes</button>
                            <button type="button" x-on:click="confirmDelete = false"
                                    class="px-2 py-0.5 rounded bg-gray-100 text-gray-600 hover:bg-gray-200">No</button>
                        </span>
                    </template>
                </div>
            </div>

            <div x-show="editOpen" class="border-t border-gray-100 bg-gray-50 px-4 py-4">
                <div class="max-w-lg space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                        <input type="text" x-model="name"
                               x-on:input="if (autoSlug) slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')"
                               class="w-full rounded border border-gray-300 text-sm px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Slug</label>
                        @if(count($item['locked_by']) > 0)
                            <span class="text-sm font-mono text-gray-500 px-3 py-1.5 bg-gray-100 rounded border border-gray-200 select-all">{{ $item['slug'] }}</span>
                        @else
                            <input type="text" x-model="slug" x-on:input="autoSlug=false"
                                   class="w-full rounded border border-gray-300 text-sm font-mono px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500 text-gray-600" />
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="cfo_active_{{ $item['id'] }}" @checked($item['active'])
                               x-on:change="$wire.toggleActive({{ $item['id'] }})"
                               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                        <label for="cfo_active_{{ $item['id'] }}" class="text-sm font-medium text-gray-700 cursor-pointer">Active</label>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <button type="button"
                                x-on:click="$wire.updateItem({{ $item['id'] }}, name, slug); editOpen=false"
                                class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500">
                            Save Changes
                        </button>
                        <button type="button" x-on:click="reset()"
                                class="px-4 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">
                            Cancel
                        </button>
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

