{{--
    ISBNdb detail modal — full book details with accept/close actions.

    Expected Alpine context (parent must provide):
        detailOpen  — bool   — whether the modal is visible
        detailItem  — object — the ISBNdb result being viewed
        detailIndex — int    — index in the results array

    Usage (include once, outside the results loop):
        <x-requests::isbndb-detail-modal wire-method="acceptIsbndbMatch" />

    Props:
        wireMethod — string — Livewire method for "Yes, this is it" (default: acceptIsbndbMatch)
--}}
@props([
    'wireMethod' => 'acceptIsbndbMatch',
])

<div
    x-show="detailOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
    @keydown.escape.window="detailOpen = false"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0" style="background:rgba(17,24,39,0.6);" @click="detailOpen = false" aria-hidden="true"></div>

    {{-- Panel --}}
    <div class="relative w-full max-w-lg flex flex-col bg-white rounded-xl overflow-hidden"
         style="max-height:85vh; box-shadow:0 25px 60px rgba(0,0,0,0.35), 0 8px 20px rgba(0,0,0,0.2);">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 flex-shrink-0">
            <h2 class="text-base font-semibold text-gray-900">Book Details</h2>
            <button type="button" @click="detailOpen = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="overflow-y-auto flex-1 px-5 py-4">
            <template x-if="detailItem">
                <div class="space-y-4">
                    {{-- Cover + core info --}}
                    <div class="flex gap-4">
                        <template x-if="detailItem.cover_url">
                            <img :src="detailItem.cover_url"
                                 :alt="'Cover of ' + detailItem.title"
                                 class="w-24 h-auto object-contain rounded shrink-0 shadow-sm"
                                 x-on:error="var f=detailItem.image;if(f&&f!==detailItem.cover_url){$el.onerror=null;$el.src=f;}else{$el.style.display='none';}" />
                        </template>
                        <div class="min-w-0">
                            {{-- Full title (title_long when available) --}}
                            <p class="font-semibold text-gray-900 text-sm leading-snug"
                               x-text="detailItem.title_long && detailItem.title_long !== detailItem.title ? detailItem.title_long : detailItem.title"></p>
                            <p class="text-gray-600 text-sm mt-1" x-text="detailItem.author_string"></p>

                            <div class="mt-2 space-y-0.5 text-xs text-gray-500">
                                <template x-if="detailItem.publisher || detailItem.publish_date">
                                    <p>
                                        <span x-text="detailItem.publisher"></span>
                                        <template x-if="detailItem.publisher && detailItem.publish_date">
                                            <span> · </span>
                                        </template>
                                        <span x-text="detailItem.publish_date"></span>
                                    </p>
                                </template>
                                <template x-if="detailItem.isbn13">
                                    <p class="font-mono">ISBN <span x-text="detailItem.isbn13"></span></p>
                                </template>
                                <template x-if="detailItem.binding">
                                    <p>
                                        <span
                                            :class="detailItem.binding && detailItem.binding.toLowerCase().includes('large print')
                                                ? 'inline-block px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-300'
                                                : 'inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600'"
                                            x-text="detailItem.binding">
                                        </span>
                                    </p>
                                </template>
                                <template x-if="detailItem.pages">
                                    <p><span x-text="detailItem.pages"></span> pages</p>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Synopsis / Overview --}}
                    <template x-if="detailItem.synopsis || detailItem.overview">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Description</h3>
                            <div class="text-sm text-gray-700 leading-relaxed [&_p]:mb-2 [&_b]:font-semibold [&_strong]:font-semibold [&_i]:italic [&_em]:italic"
                               x-html="detailItem.synopsis || detailItem.overview"></div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <button type="button"
                    @click="detailOpen = false"
                    class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-100">
                Close
            </button>
            <button type="button"
                    @click="detailOpen = false; $wire.{{ $wireMethod }}(detailIndex)"
                    class="px-5 py-2 text-sm text-white bg-green-600 rounded hover:bg-green-700 font-medium">
                Yes, this is it
            </button>
        </div>
    </div>
</div>
