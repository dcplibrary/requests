@extends('requests::staff.settings._layout')
@section('title', 'New Field')
@section('settings-content')
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.settings.form-fields', ['tab' => $formSlug]) }}" class="text-sm text-blue-600 hover:underline">&larr; Forms</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">New Field</h1>
</div>

<div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
    <form method="POST" action="{{ route('request.staff.settings.form-fields.store') }}">
        @csrf

        <div class="space-y-4">
            {{-- Label --}}
            <div>
                <label for="label" class="block text-sm font-medium text-gray-700 mb-1">Label <span class="text-red-500">*</span></label>
                <input type="text" name="label" id="label" value="{{ old('label') }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                       oninput="autoKey(this.value)">
                @error('label')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Key --}}
            <div>
                <label for="key" class="block text-sm font-medium text-gray-700 mb-1">Key</label>
                <input type="text" name="key" id="key" value="{{ old('key') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono"
                       placeholder="auto-generated from label">
                <p class="mt-1 text-xs text-gray-400">Lowercase, underscores only (e.g. <code>where_heard</code>). Leave blank to auto-generate.</p>
                @error('key')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Type --}}
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" id="type" required
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    @foreach(['text' => 'Text', 'textarea' => 'Textarea', 'html' => 'Rich Text', 'date' => 'Date', 'number' => 'Number', 'checkbox' => 'Checkbox', 'select' => 'Dropdown (select)', 'radio' => 'Radio buttons'] as $val => $lbl)
                        <option value="{{ $val }}" {{ old('type', 'text') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Select/radio options can be managed after creation.</p>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Scope --}}
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-1">Scope <span class="text-red-500">*</span></legend>
                <div class="flex flex-col gap-2">
                    @foreach(['sfp' => request_form_name('sfp') . ' only', 'ill' => request_form_name('ill') . ' only', 'both' => 'Both forms'] as $val => $lbl)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="scope" value="{{ $val }}"
                                   {{ old('scope', $formSlug) === $val ? 'checked' : '' }}
                                   class="text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-800">{{ $lbl }}</span>
                        </label>
                    @endforeach
                </div>
                @error('scope')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- Required --}}
            <div class="flex items-center gap-2">
                <input type="hidden" name="required" value="0">
                <input type="checkbox" name="required" id="required" value="1"
                       {{ old('required') ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="required" class="text-sm font-medium text-gray-700">Required</label>
            </div>

            {{-- Active --}}
            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" id="active" value="1"
                       {{ old('active', '1') ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="active" class="text-sm font-medium text-gray-700">Active</label>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                Create Field
            </button>
            <a href="{{ route('request.staff.settings.form-fields', ['tab' => $formSlug]) }}" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>

<script>
    /** Auto-fill the key field from label (snake_case). */
    function autoKey(label) {
        var keyInput = document.getElementById('key');
        if (keyInput.dataset.manual === '1') return;
        keyInput.value = label.trim().toLowerCase()
            .replace(/[^a-z0-9\s_]/g, '')
            .replace(/\s+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_|_$/g, '');
    }
    document.getElementById('key').addEventListener('input', function () {
        this.dataset.manual = this.value.length > 0 ? '1' : '0';
    });
</script>
@endsection
