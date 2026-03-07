<?php

namespace Dcplibrary\Sfp\Http\Middleware;

use Closure;
use Dcplibrary\Sfp\Models\User as SfpUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user has an SFP staff role (admin, selector, or ill).
 *
 * Resolution order:
 *  1. No authenticated user → let the host app's auth middleware handle it (pass through).
 *  2. Authenticated but no matching SFP user record → attempt to auto-provision from host app role.
 *  3. Host app user has an allowed role → create sfp_users record (active, same role) and proceed.
 *  4. No sfp_users record and no allowed host-app role → show no-access view.
 *  5. sfp_users record exists but role is neither 'admin' nor 'selector' nor 'ill' → show no-access view.
 *  6. sfp_users record exists but active = false → show no-access view.
 *  7. Role is admin, selector, or ill and active = true → proceed.
 */
class RequireSfpRole
{
    /** Roles that are permitted to access the staff area. */
    private const ALLOWED_ROLES = ['admin', 'selector', 'ill'];

    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->user();

        // Not authenticated — let the upstream auth middleware redirect to login.
        if (! $authUser instanceof Authenticatable) {
            return $next($request);
        }

        $sfpUser = $this->resolveSfpUser($authUser);

        if (! $sfpUser || ! in_array($sfpUser->role, self::ALLOWED_ROLES, true) || ! $sfpUser->active) {
            return response()->view('sfp::staff.no-access', [
                'appName' => config('app.name'),
            ], 403);
        }

        return $next($request);
    }

    /**
     * Resolve or auto-provision an SfpUser from the authenticated host-app user.
     *
     * If no sfp_users record exists but the host app user has an allowed role
     * (e.g. set by the Entra SSO package via ENTRA_GROUP_ROLES), a new sfp_users
     * record is created automatically so the user can access the staff area without
     * an admin needing to manually add them first.
     */
    private function resolveSfpUser(Authenticatable $user): ?SfpUser
    {
        // Already an SfpUser — use directly.
        if ($user instanceof SfpUser) {
            return $user;
        }

        $email = $user->email ?? null;
        if (! is_string($email) || $email === '') {
            return null;
        }

        // Look up existing sfp_users record.
        $sfpUser = SfpUser::where('email', $email)->first();

        if ($sfpUser) {
            return $sfpUser;
        }

        // No sfp_users record — check if the host app user has an allowed role
        // (populated by the Entra SSO package from ENTRA_GROUP_ROLES) and if so,
        // auto-provision a sfp_users record so they can log in immediately.
        $hostRole = $user->role ?? null;
        if (! is_string($hostRole) || ! in_array($hostRole, self::ALLOWED_ROLES, true)) {
            return null;
        }

        return SfpUser::create([
            'name'   => $user->name ?? $email,
            'email'  => $email,
            'role'   => $hostRole,
            'active' => true,
        ]);
    }
}
