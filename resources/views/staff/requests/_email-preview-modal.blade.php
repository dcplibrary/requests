{{--
  Email Preview Modal
  -------------------
  Included inside the "Update Status" div in show.blade.php.
  Controlled by the Alpine `statusUpdateForm()` function defined in show.blade.php.

  Props expected in the Alpine component's `emailPreview` object:
    show          — boolean, whether the modal is visible
    subject       — email subject string
    body          — email HTML body string
    to            — patron email address
    staffEmail    — staff user's email (for copy-to-self)
    editingEnabled — boolean from settings
--}}
<div
    x-show="emailPreview.show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="emailPreview.show = false"></div>

    {{-- Modal panel --}}
    <div class="relative w-full max-w-2xl max-h-[90vh] flex flex-col bg-white rounded-xl shadow-2xl overflow-hidden">

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
                       :class="emailPreview.editingEnabled ? 'bg-white border-blue-400 focus:ring-blue-500' : 'bg-gray-50'"
                       class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
            </div>

            {{-- Subject --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Subject</label>
                <input type="text"
                       x-model="emailPreview.subject"
                       :readonly="!emailPreview.editingEnabled"
                       :class="emailPreview.editingEnabled ? 'bg-white border-blue-400 focus:ring-blue-500' : 'bg-gray-50'"
                       class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
            </div>

            {{-- CC --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    CC <span class="text-gray-400 font-normal">(comma-separated)</span>
                </label>
                <input type="text"
                       x-model="emailPreview.cc"
                       :readonly="!emailPreview.editingEnabled"
                       placeholder="e.g. manager@library.org, team@library.org"
                       :class="emailPreview.editingEnabled ? 'bg-white border-blue-400 focus:ring-blue-500' : 'bg-gray-50'"
                       class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
            </div>

            {{-- BCC --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    BCC <span class="text-gray-400 font-normal">(comma-separated)</span>
                </label>
                <input type="text"
                       x-model="emailPreview.bcc"
                       :readonly="!emailPreview.editingEnabled"
                       placeholder="e.g. archive@library.org"
                       :class="emailPreview.editingEnabled ? 'bg-white border-blue-400 focus:ring-blue-500' : 'bg-gray-50'"
                       class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
            </div>

            {{-- Body --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Body (HTML)</label>
                <textarea rows="10"
                          x-model="emailPreview.body"
                          :readonly="!emailPreview.editingEnabled"
                          :class="emailPreview.editingEnabled ? 'bg-white border-blue-400 focus:ring-blue-500' : 'bg-gray-50'"
                          class="w-full text-sm font-mono border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300 resize-y"></textarea>
            </div>

            {{-- Copy to self --}}
            <div x-show="emailPreview.staffEmail" class="flex items-center gap-2">
                <input type="checkbox"
                       id="email_copy_to_self"
                       x-model="emailPreview.copyToSelf"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="email_copy_to_self" class="text-sm text-gray-700">
                    Send me a copy
                    <span class="text-gray-400" x-text="emailPreview.staffEmail ? '(' + emailPreview.staffEmail + ')' : ''"></span>
                </label>
            </div>

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <button type="button"
                    @click="cancelEmail()"
                    class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-100">
                Cancel (skip email)
            </button>
            <button type="button"
                    @click="sendWithEmail()"
                    class="px-5 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700 font-medium">
                Send Status &amp; Email
            </button>
        </div>

    </div>
</div>
