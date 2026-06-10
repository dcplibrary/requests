{{--
    Patron authentication nav — shown in the patron-facing layout header.

    Logged out: a "Sign in" button that opens an Alpine modal containing
                the PatronPinLogin Livewire component.
    Logged in:  a person icon + masked barcode with a dropdown showing
                "My Requests" and "Sign out".
--}}
@php
    $barcode    = session('requests_authenticated_barcode');
    $isLoggedIn = ! empty($barcode);
    $masked     = $isLoggedIn ? '••••' . substr($barcode, -4) : null;
@endphp

@if($isLoggedIn)

    {{-- ── Logged in: avatar dropdown ── --}}
    <div x-data="{ open: false }" class="relative flex items-stretch">

        <button
            type="button"
            @click="open = !open"
            class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium text-white/90 hover:text-white hover:bg-black/10 transition-colors"
            :aria-expanded="open.toString()"
            aria-haspopup="true"
        >
            {{-- Heroicon: user-circle --}}
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
            </svg>
            <span class="hidden sm:inline">{{ $masked }}</span>
            {{-- Heroicon: chevron-down (mini) --}}
            <svg class="w-3 h-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        {{-- Dropdown panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="open = false"
            class="absolute right-0 top-full mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
            role="menu"
        >
            <a
                href="{{ route('request.patron.requests') }}"
                class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"
                role="menuitem"
            >
                {{-- Heroicon: queue-list --}}
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
                My Requests
            </a>

            <div class="border-t border-gray-100 my-1" role="separator"></div>

            <form method="POST" action="{{ route('request.patron.logout') }}" role="none">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 text-left"
                    role="menuitem"
                >
                    {{-- Heroicon: arrow-left-on-rectangle --}}
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15"/>
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </div>

@else

    {{-- ── Logged out: Sign in button + modal ── --}}
    <div x-data="{ open: false }" class="relative flex items-stretch">

        <button
            type="button"
            @click="open = true"
            class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium text-white/80 hover:text-white hover:bg-black/10 transition-colors"
        >
            {{-- Heroicon: key --}}
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
            </svg>
            Sign in
        </button>

        {{-- Modal backdrop --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-black/40 flex items-center justify-center p-4"
            @click.self="open = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="patron-login-title"
        >
            {{-- Modal panel --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6"
            >
                <div class="flex items-center justify-between mb-5">
                    <h2 id="patron-login-title" class="text-lg font-semibold text-gray-900">
                        Sign in with your library card
                    </h2>
                    <button
                        type="button"
                        @click="open = false"
                        class="text-gray-400 hover:text-gray-600 -mr-1"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <livewire:requests-patron-pin-login />
            </div>
        </div>
    </div>

@endif
