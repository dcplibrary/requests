@extends('sfp::staff.settings._layout')

@section('settings-content')

<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Form Fields</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            Control which fields appear on the patron suggestion form, in what order, and under what conditions.
        </p>
    </div>
</div>

@livewire('sfp-admin-form-fields')

@endsection
