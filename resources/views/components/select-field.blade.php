{{--
    Select Field Component
    ======================
    Renders a <select> dropdown with Livewire binding.

    Props:
        $name        (string) – HTML id/name attribute                              [required]
        $wireModel   (string) – Livewire model path (e.g. 'console', 'custom.key') [required]
        $options     (array)  – [value => label] map                                [required]
        $placeholder (string) – Text for the empty first option                     [default: 'Select…']
--}}
@props([
    'name',
    'wireModel',
    'options'     => [],
    'placeholder' => 'Select…',
])

<select
    id="{{ $name }}"
    wire:model.live="{{ $wireModel }}"
    class="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
>
    <option value="">{{ $placeholder }}</option>
    @foreach($options as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
</select>
