<div class="overflow-hidden rounded-lg border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Order</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Option</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label Override</th>
                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Visible</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-16"></th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
            @forelse($items as $index => $item)
            <tr class="hover:bg-gray-50 {{ ! $item['globally_active'] ? 'opacity-50' : '' }}" wire:key="copt-{{ $index }}">

                {{-- Sort buttons --}}
                <td class="px-3 py-2">
                    <x-dcpl::sort-btns :value="$index" :first="$index === 0" :last="$index === count($items) - 1" size="md" />
                </td>

                {{-- Option name --}}
                <td class="px-4 py-2">
                    <span class="font-medium text-gray-800 {{ ! $item['visible'] ? 'line-through text-gray-400' : '' }}">
                        {{ $item['name'] }}
                    </span>
                    @if(! $item['globally_active'])
                        <span class="ml-2 text-xs text-gray-400 italic">(globally inactive)</span>
                    @endif
                    <div class="text-xs text-gray-400 font-mono">{{ $item['slug'] }}</div>
                </td>

                {{-- Label override --}}
                <td class="px-4 py-2 text-sm text-gray-500 italic">
                    {{ $item['label_override'] ?: '—' }}
                </td>

                {{-- Visible toggle --}}
                <td class="px-4 py-2 text-center">
                    <button
                        type="button"
                        wire:click="toggleVisible({{ $item['id'] }})"
                        class="inline-flex items-center justify-center w-8 h-5 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 {{ $item['visible'] ? 'bg-indigo-600' : 'bg-gray-200' }}"
                        title="{{ $item['visible'] ? 'Visible — click to hide' : 'Hidden — click to show' }}"
                        aria-label="{{ $item['visible'] ? 'Visible' : 'Hidden' }}"
                    >
                        <span class="w-3.5 h-3.5 rounded-full bg-white shadow transition-transform {{ $item['visible'] ? 'translate-x-1' : '-translate-x-1' }}"></span>
                    </button>
                </td>

                {{-- Edit link --}}
                <td class="px-4 py-2 text-right">
                    <a
                        href="{{ $this->editUrl($item['id']) }}"
                        class="text-xs text-blue-600 hover:text-blue-800 hover:underline"
                    >
                        Edit
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-400">No options found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
