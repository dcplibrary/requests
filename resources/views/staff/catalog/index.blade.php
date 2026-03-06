@extends('sfp::staff.settings._layout')
@section('title', 'Catalog Settings')
@section('settings-content')

<form method="POST" action="{{ route('sfp.staff.catalog.update') }}">
    @csrf @method('PATCH')

    {{-- Catalog + ISBNdb + Syndetics settings --}}
    @php $i = 0; @endphp
    @forelse($settings as $group => $items)
    <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">{{ ucfirst($group) }}</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($items as $setting)
            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $setting->key }}">
            <div class="px-5 py-4 flex items-start gap-6">
                <div class="w-64 flex-shrink-0">
                    <label class="block text-sm font-medium text-gray-800">{{ $setting->label ?? $setting->key }}</label>
                    @if($setting->description)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $setting->description }}</p>
                    @endif
                </div>
                <div class="flex-1">
                    @if($setting->type === 'boolean')
                        <input type="hidden" name="settings[{{ $i }}][value]" value="0">
                        <input type="checkbox"
                               name="settings[{{ $i }}][value]"
                               value="1"
                               {{ $setting->value ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    @elseif($setting->type === 'integer')
                        @php $isDays = str_ends_with($setting->key, '_days'); @endphp
                        <div class="flex items-center gap-2">
                            <input type="number"
                                   name="settings[{{ $i }}][value]"
                                   value="{{ old("settings.{$i}.value", $setting->value) }}"
                                   min="0"
                                   class="w-32 border border-gray-300 rounded px-3 py-2 text-sm">
                            @if($isDays)
                                <span class="text-sm text-gray-500">days</span>
                            @endif
                        </div>
                    @else
                        <input type="text"
                               name="settings[{{ $i }}][value]"
                               value="{{ old("settings.{$i}.value", $setting->value) }}"
                               class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    @endif
                </div>
            </div>
            @php $i++; @endphp
            @endforeach
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-400 mb-6">
        No catalog settings found. Run the seeders to populate defaults.
    </div>
    @endforelse

    {{-- Format Labels --}}
    <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">BiblioCommons Format Labels</h2>
                <p class="text-xs text-gray-400 mt-0.5">Override the raw format codes shown in catalog results (e.g. BK → Book).</p>
            </div>
            <button type="button" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                + Add Format Label
            </button>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-2 text-left font-medium">Format Code</th>
                    <th class="px-5 py-2 text-left font-medium">Display Label</th>
                    <th class="px-5 py-2 text-right font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($formatLabels as $j => $fl)
                <tr>
                    <td class="px-5 py-3">
                        <input type="hidden" name="format_labels[{{ $j }}][id]" value="{{ $fl->id }}">
                        <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">{{ $fl->format_code }}</code>
                    </td>
                    <td class="px-5 py-3">
                        <input type="text"
                               name="format_labels[{{ $j }}][label]"
                               value="{{ old("format_labels.{$j}.label", $fl->label) }}"
                               class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                    </td>
                    <td class="px-5 py-3 text-right">
                        <x-sfp::icon-btn variant="delete" label="Remove" data-delete-fl="{{ $fl->id }}" />
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

    </div>

    <div class="flex justify-end mb-8">
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
            Save Catalog Settings
        </button>
    </div>
</form>

{{-- Delete forms for each format label (outside the main PATCH form) --}}
@foreach($formatLabels as $fl)
<form class="hidden" method="POST"
      action="{{ route('sfp.staff.catalog.format-labels.destroy', $fl) }}"
      id="delete-fl-{{ $fl->id }}">
    @csrf @method('DELETE')
</form>
@endforeach

<script>
document.querySelectorAll('[data-delete-fl]').forEach(btn => {
    btn.addEventListener('click', () => {
        if (confirm('Remove this format code?')) {
            document.getElementById('delete-fl-' + btn.dataset.deleteFl).submit();
        }
    });
});
</script>


@endsection
