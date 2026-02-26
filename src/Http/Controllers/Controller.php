<?php

namespace Dcplibrary\Sfp\Http\Controllers;

use Dcplibrary\Sfp\Models\User as SfpUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * Resolve the authenticated request user to an SFP staff user record.
     *
     * Staff auth is typically handled by the host app. When the authenticated
     * user is not the package's User model, we map by email to `sfp_users` so
     * roles/groups and audit logging still work.
     */
    protected function currentSfpUser(Request $request): ?SfpUser
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            return null;
        }

        if ($user instanceof SfpUser) {
            return $user;
        }

        $email = $user->email ?? null;
        if (! is_string($email) || $email === '') {
            return null;
        }

        return SfpUser::where('email', $email)->first();
    }
}
