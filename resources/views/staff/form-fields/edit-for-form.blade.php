@extends('sfp::staff.settings._layout')
@section('title', 'Edit field for ' . $formLabel . ': ' . $field->label)
@section('settings-content')

<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.settings.form-fields') }}" class="text-sm text-blue-600 hover:underline">&larr; Forms</a>
    <span class="text-gray-300">/</span>
    <span class="text-gray-600">{{ $formLabel }}</span>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $field->label }}</h1>
</div>

@livewire('sfp-admin-form-form-field-edit', [
    'pivotId'   => $pivot->id,
    'fieldId'   => $field->id,
    'formSlug'  => $formSlug,
])

@endsection
