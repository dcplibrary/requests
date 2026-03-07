@extends('sfp::staff.settings._layout')

@section('settings-content')

<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Custom Fields</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            Add and manage dynamic fields for the SFP and ILL patron forms (labels, required/active, tokens, filters, conditional logic).
        </p>
    </div>
</div>

@livewire('sfp-admin-custom-fields')

@endsection

