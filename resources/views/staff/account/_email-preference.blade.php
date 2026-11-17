@php
    $submitted = old('receive_notification_emails');
    $receivesEmails = $submitted === null
        ? $user->wantsNotificationEmails()
        : filter_var($submitted, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
        <h2 class="text-sm font-semibold text-gray-700">Your notifications</h2>
        <p class="text-xs text-gray-400 mt-0.5">Activity emails are sent to you by default. Uncheck this if you do not want to receive them.</p>
    </div>
    <form method="POST" action="{{ route('request.staff.account.update') }}" class="px-5 py-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="receive_notification_emails" value="0">
        <label for="receive_notification_emails" class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox"
                   id="receive_notification_emails"
                   name="receive_notification_emails"
                   value="1"
                   {{ $receivesEmails ? 'checked' : '' }}
                   class="w-4 h-4 rounded border-gray-300 text-blue-600">
            <span class="text-sm font-medium text-gray-800">Send notification emails</span>
        </label>
        <p class="text-xs text-gray-400 mt-2">Includes emails when a request is routed to one of your groups or assigned to you.</p>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                Save
            </button>
        </div>
    </form>
</div>
