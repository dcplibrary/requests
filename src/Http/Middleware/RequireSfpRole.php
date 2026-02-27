<?php

namespace Dcplibrary\Sfp\Http\Middleware;

use Closure;
use Dcplibrary\Sfp\Models\User as SfpUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user has an SFP staff role (admin or selector).
 *
 * Resolution order:
 *  1. No authenticated user → let the host app's auth middleware handle it (pass through).
 *  2. Authenticated but no matching SFP user record (unknown email) → show no-access view.
 *  3. SFP user exists but role is neither 'admin' nor 'selector' → show no-access view.
 *  4. SFP user is inactive → show no-access view.
 *  5. Role is admin or selector → proceed.
 */
class RequireSfpRole
{
    /** Roles that are permitted to access the staff area. */
    private const ALLOWED_ROLES = ['admin', 'selector'];

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

    private function resolveSfpUser(Authenticatable $user): ?SfpUser
    {
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
