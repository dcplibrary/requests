<div class="space-y-6">

    {{-- Label override --}}
    <div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
        <div class="space-y-4">
            <div>
                <label for="fffo_label" class="block text-sm font-medium text-gray-700 mb-1">Label Override</label>
                <input
                    type="text"
                    id="fffo_label"
                    wire:model="labelOverride"
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Override for this form only — leave blank to use &quot;{{ $optionName }}&quot;"
                />
                <p class="mt-1 text-xs text-gray-400">Overrides the display name of this option on this form only. The global name is managed in Settings &rarr; Material Types / Audiences / etc.</p>
            </div>
        </div>
    </div>

    {{-- ISBNdb search (material types only) --}}
    @if($isMaterialType)
    <div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center gap-2">
            <input
                type="checkbox"
                id="fffo_isbndb"
                wire:model="isbndbSearchable"
                class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <label for="fffo_isbndb" class="text-sm font-medium text-gray-700 cursor-pointer">Search ISBNdb for this material type</label>
        </div>
        <p class="mt-2 text-xs text-gray-400">When enabled, submitting a {{ $optionName }} request will search ISBNdb for enrichment data. Applies globally across all forms.</p>
    </div>
    @endif

    {{-- Visible --}}
    <div class="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center gap-2">
            <input
                type="checkbox"
                id="fffo_visible"
                wire:model="visible"
                class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <label for="fffo_visible" class="text-sm font-medium text-gray-700 cursor-pointer">Visible</label>
            <span class="text-xs text-gray-400">— show this option on this form</span>
        </div>
        <p class="mt-2 text-xs text-gray-400">When hidden, the option will not appear in the patron's dropdown / selector for this form. The global option is unaffected.</p>
    </div>

    <div class="flex items-center gap-3">
        <button
            type="button"
            wire:click="save"
            class="px-5 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
        >
            Save Changes
        </button>
        <a
            href="{{ route('request.staff.settings.form-fields.edit-for-form', ['field' => $fieldId, 'form' => $formSlug]) }}"
            class="px-5 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200"
        >
            Cancel
        </a>
    </div>

</div>
