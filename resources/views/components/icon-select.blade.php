{{--
    Icon selector — solid and outline Heroicons (outline names end with -outline).

    Usage:
        <x-requests::icon-select name="icon" :value="$model->icon" />

    Props:
        name  — string       — form input name
        value — string|null  — currently selected icon name
--}}
@props(['name' => 'icon', 'value' => null])

@php
    $iconNamesSolid = [
        'clock'                   => 'Clock',
        'check-circle'            => 'Check Circle',
        'x-circle'                => 'X Circle',
        'exclamation-circle'      => 'Exclamation Circle',
        'question-mark-circle'    => 'Question Mark',
        'information-circle'      => 'Information',
        'arrow-path'              => 'Arrow Path',
        'pause-circle'            => 'Pause',
        'play-circle'             => 'Play',
        'stop-circle'             => 'Stop',
        'magnifying-glass'        => 'Search',
        'eye'                     => 'Eye',
        'eye-slash'               => 'Eye Slash',
        'sparkles'                => 'Sparkles',
        'shopping-bag'            => 'Shopping Bag',
        'shopping-cart'           => 'Shopping Cart',
        'truck'                   => 'Truck',
        'envelope'                => 'Envelope',
        'archive-box'             => 'Archive',
        'no-symbol'               => 'No Symbol',
        'flag'                    => 'Flag',
        'star'                    => 'Star',
        'bolt'                    => 'Bolt',
        'bell'                    => 'Bell',
        'bell-alert'              => 'Bell Alert',
        'hand-thumb-up'           => 'Thumb Up',
        'hand-thumb-down'         => 'Thumb Down',
        'bookmark'                => 'Bookmark',
        'paper-airplane'          => 'Paper Airplane',
        'document-check'          => 'Doc Check',
        'clipboard-document-list' => 'Clipboard',
        'cog-6-tooth'             => 'Settings',
        'document-text'           => 'Document',
        'book-open'               => 'Book Open',
        'user-group'              => 'User Group',
        'users'                   => 'Users',
        'circle-stack'            => 'Database',
        'barcode'                 => 'Barcode',
        'arrow-up'                => 'Arrow Up',
        'arrow-down'              => 'Arrow Down',
        'arrow-left'              => 'Arrow Left',
        'arrow-right'             => 'Arrow Right',
        'arrow-up-right'          => 'Arrow Up Right',
        'arrow-down-right'        => 'Arrow Down Right',
        'arrow-up-left'           => 'Arrow Up Left',
        'arrow-down-left'         => 'Arrow Down Left',
        'arrow-long-up'           => 'Arrow Long Up',
        'arrow-long-down'         => 'Arrow Long Down',
        'arrow-long-left'         => 'Arrow Long Left',
        'arrow-long-right'        => 'Arrow Long Right',
        'arrow-uturn-left'        => 'Arrow U-Turn Left',
        'arrow-uturn-right'       => 'Arrow U-Turn Right',
        'arrow-uturn-up'          => 'Arrow U-Turn Up',
        'arrow-uturn-down'        => 'Arrow U-Turn Down',
        'arrows-pointing-in'      => 'Arrows In',
        'arrows-pointing-out'     => 'Arrows Out',
        'arrows-right-left'       => 'Arrows Left Right',
        'arrows-up-down'          => 'Arrows Up Down',
    ];
    $iconNames = $iconNamesSolid;
    foreach ($iconNamesSolid as $key => $label) {
        $iconNames[$key.'-outline'] = $label.' (outline)';
    }
@endphp

<div x-data="{ open: false, selected: '{{ old($name, $value ?? '') }}' }" @click.away="open = false" class="relative">
    <input type="hidden" name="{{ $name }}" :value="selected">

    {{-- Trigger button --}}
    <button type="button" @click="open = !open"
            class="flex items-center gap-2 w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white hover:bg-gray-50 text-left">
        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-gray-500">
            @foreach($iconNames as $key => $label)
                <span x-show="selected === '{{ $key }}'" x-cloak>
                    <x-requests::status-icon :name="$key" class="w-5 h-5" />
                </span>
            @endforeach
            <span x-show="!selected" class="text-gray-300">&mdash;</span>
        </span>
        <span class="flex-1 truncate">
            @foreach($iconNames as $key => $label)
                <span x-show="selected === '{{ $key }}'" x-cloak>{{ $label }}</span>
            @endforeach
            <span x-show="!selected" class="text-gray-400">None</span>
        </span>
        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    {{-- Dropdown grid --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2">

        {{-- Clear option --}}
        <button type="button"
                @click="selected = ''; open = false"
                class="w-full text-left px-2 py-1.5 text-xs text-gray-400 hover:bg-gray-50 rounded mb-1">
            None
        </button>

        {{-- Icon grid (solids first, then outline variants) --}}
        <div class="grid grid-cols-6 gap-1 max-h-72 overflow-y-auto">
            @foreach($iconNames as $key => $label)
                <button type="button"
                        @click="selected = '{{ $key }}'; open = false"
                        :class="selected === '{{ $key }}' ? 'bg-blue-50 ring-1 ring-blue-300' : 'hover:bg-gray-50'"
                        class="flex items-center justify-center w-full aspect-square rounded p-1 transition-colors"
                        title="{{ $label }}">
                    <x-requests::status-icon :name="$key" class="w-5 h-5 text-gray-600" />
                </button>
            @endforeach
        </div>
    </div>
</div>
