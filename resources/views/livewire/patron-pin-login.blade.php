<div>
    {{-- Auth failure alert --}}
    @if($failed)
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md" role="alert">
        <p class="text-sm text-red-800">
            We couldn't verify your credentials. Please check your library card number and PIN and try again.
        </p>
    </div>
    @endif

    <div class="space-y-4">

        {{-- Library card number --}}
        <div>
            <label for="pin-barcode" class="block text-sm font-medium text-gray-700 mb-1">
                Library Card Number <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input
                type="text"
                id="pin-barcode"
                wire:model="barcode"
                autocomplete="off"
                class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('barcode') ? 'border-red-500' : 'border-gray-300' }}"
                aria-required="true"
            />
            @error('barcode')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        {{-- PIN --}}
        <div>
            <label for="pin-pin" class="block text-sm font-medium text-gray-700 mb-1">
                PIN <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input
                type="password"
                id="pin-pin"
                wire:model="pin"
                autocomplete="current-password"
                inputmode="numeric"
                pattern="[0-9]{4,6}"
                maxlength="6"
                class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('pin') ? 'border-red-500' : 'border-gray-300' }}"
                aria-required="true"
            />
            @error('pin')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

    </div>

    <div class="mt-7 flex items-center justify-end gap-3">
        <button
            type="button"
            @click="open = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
            Cancel
        </button>
        <button
            type="button"
            wire:click="login"
            wire:loading.attr="disabled"
            class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            <span wire:loading.remove wire:target="login">Sign In</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </div>
</div>
