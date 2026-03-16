@props([
    'title' => 'Patron Information',
    'subtitle' => null,
    'showNotifyByEmail' => false,
    'showBarcodeNotFound' => false,
    'barcodeNotFoundMessage' => '',
])

<section aria-labelledby="patron-heading">
    @if($subtitle !== null && $subtitle !== '')
        <h1 id="patron-heading" class="text-2xl font-bold text-gray-900 mb-2">{{ $title }}</h1>
        <p class="text-sm text-gray-600 mb-6">{{ $subtitle }}</p>
    @else
        <h2 id="patron-heading" class="text-2xl font-bold text-gray-900 mb-6">{{ $title }}</h2>
    @endif

    <div class="space-y-5">
        {{-- Barcode --}}
        <div>
            <label for="barcode" class="block text-sm font-medium text-gray-700 mb-1">
                Library Card Barcode Number <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input
                type="text"
                id="barcode"
                wire:model="barcode"
                class="w-full max-w-xs rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('barcode') || $showBarcodeNotFound ? 'border-red-500' : 'border-gray-300' }}"
                autocomplete="off"
                aria-required="true"
                aria-describedby="{{ $errors->has('barcode') ? 'barcode-error' : ($showBarcodeNotFound ? 'barcode-not-found' : '') }}"
            />
            @error('barcode')
                <p id="barcode-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            @if($showBarcodeNotFound && $barcodeNotFoundMessage !== '')
                <div id="barcode-not-found"
                     class="mt-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 prose prose-sm max-w-none [&_a]:text-red-700 [&_a]:underline"
                     role="alert"
                     aria-live="assertive">
                    {!! $barcodeNotFoundMessage !!}
                </div>
            @endif
        </div>

        {{-- Name --}}
        <fieldset>
            <legend class="block text-sm font-medium text-gray-700 mb-1">
                Name <span class="text-red-600" aria-hidden="true">*</span>
            </legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="name_first" class="sr-only">First name</label>
                    <input
                        type="text"
                        id="name_first"
                        wire:model="name_first"
                        placeholder="First"
                        autocomplete="given-name"
                        class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('name_first') ? 'border-red-500' : 'border-gray-300' }}"
                        aria-required="true"
                        aria-label="First name"
                    />
                    @error('name_first')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="name_last" class="sr-only">Last name</label>
                    <input
                        type="text"
                        id="name_last"
                        wire:model="name_last"
                        placeholder="Last"
                        autocomplete="family-name"
                        class="w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('name_last') ? 'border-red-500' : 'border-gray-300' }}"
                        aria-required="true"
                        aria-label="Last name"
                    />
                    @error('name_last')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        {{-- Phone --}}
        <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                Phone <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input
                type="tel"
                id="phone"
                wire:model="phone"
                autocomplete="tel"
                class="w-full max-w-xs rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('phone') ? 'border-red-500' : 'border-gray-300' }}"
                aria-required="true"
            />
            @error('phone')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autocomplete="email"
                class="w-full max-w-sm rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 border-gray-300"
            />
            @error('email')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror

            @if($showNotifyByEmail)
                <label class="inline-flex items-start gap-2 mt-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        id="notify_by_email"
                        wire:model="notify_by_email"
                        class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 shrink-0"
                    />
                    <span class="text-sm text-gray-700">
                        Notify me by email when my request is updated
                        <span class="text-gray-400 font-normal">&mdash; status updates will only be sent by email, not by phone or mail</span>
                    </span>
                </label>
            @endif
        </div>
    </div>
</section>
