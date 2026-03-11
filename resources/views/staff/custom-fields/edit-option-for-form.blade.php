@extends('requests::staff.settings._layout')
@section('title', 'Edit option for ' . $formLabel . ': ' . $field->label)
@section('settings-content')

<div class="mb-6 flex items-center gap-3 flex-wrap">
    <a href="{{ route('request.staff.settings.form-fields') }}" class="text-sm text-blue-600 hover:underline">&larr; Forms</a>
    <span class="text-gray-300">/</span>
    <a href="{{ route('request.staff.settings.custom-fields.edit-for-form', ['field' => $field->id, 'form' => $formSlug]) }}" class="text-sm text-blue-600 hover:underline">{{ $formLabel }} &rarr; {{ $field->label }}</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $optionName }}</h1>
</div>

@livewire('requests-admin-form-custom-field-option-edit', [
    'fieldId'   => $field->id,
    'optionId'  => $optionId,
    'formSlug'  => $formSlug,
])

@endsection
