{{--
  Email Preview Modal
  -------------------
  Included inside the "Update Status" div in show.blade.php.
  Controlled by the Alpine `statusUpdateForm()` function defined in show.blade.php.

  The body is rendered in a Trix editor (read-only or editable per email_editing_enabled setting).
  The "Send me a copy" checkbox lives in the footer so it's always visible without scrolling.
--}}
<div
    x-show="emailPreview.show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0" style="background:rgba(17,24,39,0.6);" @click="emailPreview.show = false"></div>

    {{-- Modal panel --}}
    <div class="relative w-full max-w-2xl flex flex-col bg-white rounded-xl overflow-hidden"
         style="max-height:90vh; box-shadow:0 25px 60px rgba(0,0,0,0.35), 0 8px 20px rgba(0,0,0,0.2);">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 flex-shrink-0">
            <h2 class="text-base font-semibold text-gray-900">Email Preview</h2>
            <button type="button"
                    @click="emailPreview.show = false"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="overflow-y-auto flex-1 px-5 py-4 space-y-4">

            {{-- To --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input type="email"
                       x-model="emailPreview.to"
                       :readonly="!emailPreview.editingEnabled"
                       :class="emailPreview.editingEnabled ? 'bg-white border-blue-400' : 'bg-gray-50 text-gray-700'"
                       class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none">
            </div>

            {{-- Subject --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Subject</label>
                <input type="text"
                       x-model="emailPreview.subject"
                       :readonly="!emailPreview.editingEnabled"
                       :class="emailPreview.editingEnabled ? 'bg-white border-blue-400' : 'bg-gray-50 text-gray-700'"
                       class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none">
            </div>

            {{-- CC / BCC — only shown when editing is on --}}
            <div x-show="emailPreview.editingEnabled" class="space-y-3" style="display:none;">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        CC <span class="text-gray-400 font-normal">(comma-separated)</span>
                    </label>
                    <input type="text"
                           x-model="emailPreview.cc"
                           placeholder="e.g. manager@library.org"
                           class="w-full text-sm border border-blue-400 bg-white rounded px-3 py-2 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        BCC <span class="text-gray-400 font-normal">(comma-separated)</span>
                    </label>
                    <input type="text"
                           x-model="emailPreview.bcc"
                           placeholder="e.g. archive@library.org"
                           class="w-full text-sm border border-blue-400 bg-white rounded px-3 py-2 focus:outline-none">
                </div>
            </div>

            {{-- Email body — single Trix editor (read-only or editable per setting) --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Message</label>
                <div class="rounded border overflow-hidden"
                     :class="emailPreview.editingEnabled ? 'sfp-ep-editing' : 'sfp-ep-readonly'"
                     :style="emailPreview.editingEnabled ? 'border-color:#60a5fa;' : 'border-color:#e5e7eb;'">
                    <input type="hidden" id="sfp-email-preview-body">
                    <trix-editor
                        input="sfp-email-preview-body"
                        :contenteditable="emailPreview.editingEnabled ? 'true' : 'false'"
                        class="trix-content bg-white text-sm"
                        style="min-height:220px; max-height:360px; overflow-y:auto;"></trix-editor>
                </div>
                <style>
                    .sfp-ep-readonly trix-toolbar { display: none !important; }
                    .sfp-ep-readonly trix-editor  { cursor: default; }
                </style>
            </div>

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <button type="button"
                    @click="cancelEmail()"
                    class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-100">
                Skip Email
            </button>
            <div class="flex items-center gap-3">
                {{-- Copy to self --}}
                <div x-show="emailPreview.staffEmail" class="flex items-center gap-2">
                    <input type="checkbox"
                           id="email_copy_to_self"
                           x-model="emailPreview.copyToSelf"
                           class="rounded border-gray-300 text-blue-600">
                    <label for="email_copy_to_self" class="text-sm text-gray-700">Send me a copy</label>
                </div>
                <button type="button"
                        @click="sendWithEmail()"
                        class="px-5 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700 font-medium">
                    Send Email
                </button>
            </div>
        </div>

    </div>
</div>
