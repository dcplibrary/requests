@props([
    'title' => 'Patron Information',
    'subtitle' => null,
    'showNotifyByEmail' => false,
    'notifyWireModel'   => 'notify_by_email',
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

        {{-- Email + Notification --}}
        @if($showNotifyByEmail)
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-blue-500 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"/>
                    <path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-blue-900 mb-0.5">Stay informed about your request</p>
                    <p class="text-xs text-blue-700 mb-3">Add your email address and check the box below to receive a notification when your request status changes. Status updates are only sent by email.</p>

                    <div class="mb-3">
                        <label for="email" class="block text-sm font-medium text-blue-900 mb-1">Email address</label>
                        <input
                            type="email"
                            id="email"
                            wire:model="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            class="w-full max-w-sm rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 border-blue-200 bg-white"
                        />
                        @error('email')
                            <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            wire:model="{{ $notifyWireModel }}"
                            class="rounded border-blue-300 text-blue-600 focus:ring-blue-500 shrink-0"
                        />
                        <span class="text-sm font-medium text-blue-900">Notify me by email when my request is updated</span>
                    </label>
                </div>
            </div>
        </div>
        @else
        {{-- Email only (no notification checkbox) --}}
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
        </div>
        @endif
    </div>
</section>
