@extends('sfp::staff._layout')
@section('title', 'Settings')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
</div>

<form method="POST" action="{{ route('sfp.staff.settings.update') }}">
    @csrf @method('PATCH')

    @forelse($settings as $group => $items)
    <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">{{ $group ?: 'General' }}</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($items as $i => $setting)
            <input type="hidden" name="settings[{{ $loop->index + ($loop->parent ? $loop->parent->count() : 0) }}][key]" value="{{ $setting->key }}">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">{{ $setting->label ?? $setting->key }}</label>
                    @if($setting->description)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $setting->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    @if($setting->type === 'boolean')
                        <input type="hidden" name="settings[{{ $loop->index + ($loop->parent ? $loop->parent->count() : 0) }}][value]" value="0">
                        <input type="checkbox"
                               name="settings[{{ $loop->index + ($loop->parent ? $loop->parent->count() : 0) }}][value]"
                               value="1"
                               {{ $setting->value ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    @elseif($setting->type === 'textarea')
                        <textarea name="settings[{{ $loop->index + ($loop->parent ? $loop->parent->count() : 0) }}][value]"
                                  rows="4"
                                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm resize-y">{{ $setting->value }}</textarea>
                    @else
                        <input type="{{ $setting->type ?? 'text' }}"
                               name="settings[{{ $loop->index + ($loop->parent ? $loop->parent->count() : 0) }}][value]"
                               value="{{ old("settings.{$setting->key}.value", $setting->value) }}"
                               class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-400">
        No settings found. Run the seeders to populate default settings.
    </div>
    @endforelse

    @if($settings->isNotEmpty())
    <div class="flex justify-end">
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            Save Settings
        </button>
    </div>
    @endif
</form>
@endsection
