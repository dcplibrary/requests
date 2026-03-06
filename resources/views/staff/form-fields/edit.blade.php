@extends('sfp::staff.settings._layout')
@section('title', 'Edit Field: ' . $field->label)
@section('settings-content')

<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.settings.form-fields') }}" class="text-sm text-blue-600 hover:underline">&larr; Form Fields</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $field->label }}</h1>
</div>

@livewire('sfp-admin-form-field-edit', ['fieldId' => $field->id])

@endsection
