<?php

namespace Dcplibrary\Requests\Http\Controllers;

use Dcplibrary\Requests\Models\User as StaffUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * Resolve the authenticated request user to a staff user record.
     *
     * Staff auth is typically handled by the host app. When the authenticated
     * user is not the package's User model, we map by email to `staff_users` so
     * roles/groups and audit logging still work.
     */
    protected function currentStaffUser(Request $request): ?StaffUser
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            return null;
        }

        if ($user instanceof StaffUser) {
            return $user;
        }

        $email = $user->email ?? null;
        if (! is_string($email) || $email === '') {
            return null;
        }

        return StaffUser::where('email', $email)->first();
    }
}
