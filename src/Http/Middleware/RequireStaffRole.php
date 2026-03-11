<?php

namespace Dcplibrary\Requests\Http\Middleware;

use Closure;
use Dcplibrary\Requests\Models\User as StaffUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user has a staff role (admin or selector).
 *
 * ILL access is not a role; it is determined by group membership (the selector
 * group identified by ill_selector_group_id). Use User::hasIllAccess() for that.
 *
 * Resolution order:
 *  1. No authenticated user → let the host app's auth middleware handle it (pass through).
 *  2. Authenticated but no matching staff user record → attempt to auto-provision from host app role.
 *  3. Host app user has an allowed role (or legacy 'ill') → create staff_users record and proceed.
 *  4. No staff_users record and no allowed host-app role → show no-access view.
 *  5. staff_users record exists but role is not 'admin' or 'selector' → show no-access view.
 *  6. staff_users record exists but active = false → show no-access view.
 *  7. Role is admin or selector and active = true → proceed.
 */
class RequireStaffRole
{
    /** Roles that are permitted to access the staff area. */
    private const ALLOWED_ROLES = ['admin', 'selector'];

    /** Legacy host role; when provisioning we store as 'selector'. */
    private const LEGACY_ILL_ROLE = 'ill';

    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->user();

        // Not authenticated — let the upstream auth middleware redirect to login.
        if (! $authUser instanceof Authenticatable) {
            return $next($request);
        }

        $staffUser = $this->resolveStaffUser($authUser);

        $roleAllowed = in_array($staffUser->role, self::ALLOWED_ROLES, true);
        if (! $staffUser || ! $roleAllowed || ! $staffUser->active) {
            return response()->view('requests::staff.no-access', [
                'appName' => config('app.name'),
            ], 403);
        }

        return $next($request);
    }

    /**
     * Resolve or auto-provision an StaffUser from the authenticated host-app user.
     *
     * If no staff_users record exists but the host app user has an allowed role
     * (e.g. set by the Entra SSO package via ENTRA_GROUP_ROLES), a new staff_users
     * record is created automatically so the user can access the staff area without
     * an admin needing to manually add them first.
     */
    private function resolveStaffUser(Authenticatable $user): ?StaffUser
    {
        // Already an StaffUser — use directly.
        if ($user instanceof StaffUser) {
            return $user;
        }

        $email = $user->email ?? null;
        if (! is_string($email) || $email === '') {
            return null;
        }

        // Look up existing staff_users record.
        $staffUser = StaffUser::where('email', $email)->first();

        if ($staffUser) {
            return $staffUser;
        }

        // No staff_users record — check if the host app user has an allowed role
        // (populated by the Entra SSO package from ENTRA_GROUP_ROLES) and if so,
        // auto-provision a staff_users record so they can log in immediately.
        // Legacy: host role 'ill' is mapped to 'selector' (ILL access is group-based).
        $hostRole = $user->role ?? null;
        if (! is_string($hostRole)) {
            return null;
        }
        $provisionRole = $hostRole === self::LEGACY_ILL_ROLE ? 'selector' : $hostRole;
        if (! in_array($provisionRole, self::ALLOWED_ROLES, true)) {
            return null;
        }

        return StaffUser::create([
            'name'   => $user->name ?? $email,
            'email'  => $email,
            'role'   => $provisionRole,
            'active' => true,
        ]);
    }
}
