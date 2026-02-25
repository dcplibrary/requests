<?php

namespace Dcplibrary\Sfp\Http\Controllers\Auth;

use Dcplibrary\EntraSSO\EntraSSOService;
use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entra SSO Authentication Controller
 *
 * Delegates to the dcplibrary/entra-sso package's EntraSSOService for the
 * OAuth2 redirect and token exchange, but logs the user into the dedicated
 * 'sfp' guard (backed by sfp_users) instead of the host app's default guard.
 *
 * Redirect URI to register in the Entra portal:
 *   {APP_URL}/sfp/auth/callback  (or whatever SFP_ENTRA_REDIRECT_URI is set to)
 */
class EntraAuthController extends Controller
{
    public function __construct(protected EntraSSOService $sso) {}

    /**
     * Redirect to Microsoft Entra login using a SFP-specific redirect URI.
     */
    public function redirect()
    {
        // Override the redirect_uri that EntraSSOService was constructed with
        // so the callback lands on our route, not the host app's entra.callback.
        $service = $this->sfpSsoService();

        return redirect($service->getAuthorizationUrl());
    }

    /**
     * Handle the OAuth2 callback from Entra and log into the sfp guard.
     */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('sfp.login')->withErrors([
                'error' => 'Authentication failed: ' . $request->get('error_description', $request->get('error')),
            ]);
        }

        $service = $this->sfpSsoService();

        if (! $service->validateState($request->get('state'))) {
            return redirect()->route('sfp.login')->withErrors([
                'error' => 'Invalid state parameter. Please try again.',
            ]);
        }

        try {
            $tokenData = $service->getAccessToken($request->get('code'));
            $userInfo  = $service->getUserInfo($tokenData['access_token']);

            $email = $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? null;

            if (! $email) {
                return redirect()->route('sfp.login')->withErrors([
                    'error' => 'Could not retrieve email from Microsoft. Contact your administrator.',
                ]);
            }

            // Find or create the user in sfp_users.
            // New users are created with role 'selector' and active=true;
            // an admin can change their role and deactivate them in the staff UI.
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $userInfo['displayName'] ?? $email,
                    'entra_id' => $userInfo['id'] ?? null,
                    'role'     => 'selector',
                    'active'   => true,
                ]
            );

            if (! $user->active) {
                return redirect()->route('sfp.login')->withErrors([
                    'error' => 'Your account is inactive. Contact your administrator.',
                ]);
            }

            // Keep name and entra_id fresh on every login.
            $user->update(array_filter([
                'name'     => $userInfo['displayName'] ?? $user->name,
                'entra_id' => $userInfo['id'] ?? null,
            ]));

            Auth::guard(config('sfp.guard', 'sfp'))->login($user, remember: true);

            return redirect()->intended(route('sfp.staff.requests.index'));

        } catch (\Throwable $e) {
            return redirect()->route('sfp.login')->withErrors([
                'error' => 'Authentication failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Log out of the sfp guard and return to the patron form.
     */
    public function logout(Request $request)
    {
        Auth::guard(config('sfp.guard', 'sfp'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sfp.form');
    }

    /**
     * Build an EntraSSOService instance that uses the SFP-specific redirect URI.
     *
     * The host app's EntraSSOService singleton was constructed with the value of
     * config('entra-sso.redirect_uri') (i.e. /auth/entra/callback).  We need our
     * own instance pointing to /sfp/auth/callback so Microsoft sends the code here.
     */
    protected function sfpSsoService(): EntraSSOService
    {
        return new EntraSSOService(
            config('entra-sso.tenant_id'),
            config('entra-sso.client_id'),
            config('entra-sso.client_secret'),
            config('sfp.entra_redirect_uri'),
        );
    }
}
