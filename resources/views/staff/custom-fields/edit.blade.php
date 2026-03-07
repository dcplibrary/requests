@extends('sfp::staff.settings._layout')
@section('title', 'Edit Custom Field: ' . $field->label)
@section('settings-content')

<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('sfp.staff.settings.custom-fields') }}" class="text-sm text-blue-600 hover:underline">&larr; Custom Fields</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">{{ $field->label }}</h1>
</div>

@livewire('sfp-admin-custom-field-edit', ['fieldId' => $field->id])

@endsection

