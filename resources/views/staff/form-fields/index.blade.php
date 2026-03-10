@extends('sfp::staff.settings._layout')

@section('settings-content')

<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Forms</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            Suggest for Purchase and Interlibrary Loan field lists. Global fields appear in both forms. Control order, visibility, and conditions per form.
        </p>
    </div>
</div>

@livewire('sfp-admin-form-fields')

@endsection
