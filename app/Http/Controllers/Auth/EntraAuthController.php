<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entra SSO Authentication Controller
 *
 * Uses the blashbrook/entra-sso package (or Laravel Socialite with Azure provider).
 * Replace the stub methods below with the actual package's redirect/callback pattern.
 *
 * Recommended package: blashbrook/entra-sso (see its README for full config)
 * Alternative: socialiteproviders/microsoft-azure
 */
class EntraAuthController extends Controller
{
    /**
     * Redirect to Microsoft Entra login.
     */
    public function redirect()
    {
        // Example using Socialite + Azure provider:
        // return Socialite::driver('azure')->redirect();

        // Replace with blashbrook/entra-sso redirect call per its docs.
        return redirect()->route('login')->withErrors(['error' => 'SSO not yet configured.']);
    }

    /**
     * Handle the callback from Entra.
     */
    public function callback(Request $request)
    {
        // Example using Socialite + Azure provider:
        // $entraUser = Socialite::driver('azure')->user();

        // $user = User::updateOrCreate(
        //     ['entra_id' => $entraUser->getId()],
        //     [
        //         'name'  => $entraUser->getName(),
        //         'email' => $entraUser->getEmail(),
        //         'last_login_at' => now(),
        //     ]
        // );

        // if (! $user->active) {
        //     Auth::logout();
        //     return redirect()->route('login')->withErrors(['error' => 'Your account is inactive.']);
        // }

        // Auth::login($user, true);
        // return redirect()->route('staff.requests.index');

        return redirect()->route('login')->withErrors(['error' => 'SSO callback not yet implemented.']);
    }

    /**
     * Log out.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
