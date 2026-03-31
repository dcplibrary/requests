@extends('requests::staff.settings._layout')

@section('settings-content')

<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Forms</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            Suggest for Purchase and Interlibrary Loan field lists. Global fields appear in both forms. Control order, visibility, and conditions per form.
        </p>
    </div>
</div>

{{-- Form Titles --}}
@php
    $sfpTitle = \Dcplibrary\Requests\Models\Setting::where('key', 'sfp_form_title')->first();
    $illTitle = \Dcplibrary\Requests\Models\Setting::where('key', 'ill_form_title')->first();
@endphp

<form method="POST" action="{{ route('request.staff.settings.update') }}" class="mb-8">
    @csrf @method('PATCH')

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">Form Titles</h3>
        </div>
        <div class="divide-y divide-gray-100">

            {{-- SFP Title --}}
            @if($sfpTitle)
            <input type="hidden" name="settings[0][key]" value="{{ $sfpTitle->key }}">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label for="sfp_form_title" class="block text-sm font-medium text-gray-800">{{ $sfpTitle->label ?? 'SFP Form Title' }}</label>
                    @if($sfpTitle->description)
                        <p class="text-xs text-gray-500 mt-1">{{ $sfpTitle->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    <input type="text"
                           id="sfp_form_title"
                           name="settings[0][value]"
                           value="{{ old('settings.0.value', $sfpTitle->value) }}"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            @endif

            {{-- ILL Title --}}
            @if($illTitle)
            <input type="hidden" name="settings[1][key]" value="{{ $illTitle->key }}">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label for="ill_form_title" class="block text-sm font-medium text-gray-800">{{ $illTitle->label ?? 'ILL Form Title' }}</label>
                    @if($illTitle->description)
                        <p class="text-xs text-gray-500 mt-1">{{ $illTitle->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    <input type="text"
                           id="ill_form_title"
                           name="settings[1][value]"
                           value="{{ old('settings.1.value', $illTitle->value) }}"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            @endif

        </div>
        <div class="px-5 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                Save Titles
            </button>
        </div>
    </div>
</form>

@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded text-sm text-green-800">
    {{ session('success') }}
</div>
@endif

@livewire('requests-admin-form-fields')

@endsection
