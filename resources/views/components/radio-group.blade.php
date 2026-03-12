{{--
    Radio Group Component
    =====================
    Renders a set of radio buttons with Livewire binding.

    Supports two layout variants:
      - card   (default): Stacked, padded labels with selected-state highlighting.
      - inline:           Horizontal flex-wrap, compact labels.

    Props:
        $name       (string)       – HTML name attribute                     [required]
        $wireModel  (string)       – Livewire model path (e.g. 'audience_id', 'custom.genre') [required]
        $options    (array)        – [value => label] map                    [required]
        $selected   (mixed)        – Current value, used for card highlighting [default: null]
        $variant    (string)       – 'card' or 'inline'                      [default: 'card']
        $required   (bool)         – Sets aria-required on the group          [default: false]
--}}
@props([
    'name',
    'wireModel',
    'options'  => [],
    'selected' => null,
    'variant'  => 'card',
    'required' => false,
])

@php
    $isCard = $variant === 'card';
@endphp

<div
    class="{{ $isCard ? 'space-y-1' : 'flex items-center gap-4 flex-wrap' }}"
    role="radiogroup"
    @if($required) aria-required="true" @endif
>
    @foreach($options as $value => $label)
        <label class="{{
            $isCard
                ? 'flex items-center gap-3 p-2 rounded-md cursor-pointer hover:bg-gray-50'
                  . ((string) $selected === (string) $value ? ' bg-blue-50 ring-1 ring-blue-200' : '')
                : 'flex items-center gap-2 cursor-pointer'
        }}">
            <input
                type="radio"
                wire:model.live="{{ $wireModel }}"
                value="{{ $value }}"
                name="{{ $name }}"
                class="text-blue-600 focus:ring-blue-500"
            />
            <span class="text-sm text-gray-800">{{ $label }}</span>
        </label>
    @endforeach
</div>
