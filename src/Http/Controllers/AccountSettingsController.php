<?php

namespace Dcplibrary\Requests\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Personal settings for the signed-in staff user.
 */
class AccountSettingsController extends Controller
{
    /**
     * Show the signed-in user's settings.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(Request $request)
    {
        $user = $this->currentStaffUser($request);
        if (! $user) {
            abort(403);
        }

        return view('requests::staff.account.settings', [
            'user' => $user,
        ]);
    }

    /**
     * Save whether this user receives activity notification emails.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $user = $this->currentStaffUser($request);
        if (! $user) {
            abort(403);
        }

        $request->validate([
            'receive_notification_emails' => 'required|boolean',
        ]);

        $user->receive_notification_emails = $request->boolean('receive_notification_emails');
        $user->save();

        return back()->with('success', 'Settings saved.');
    }
}
