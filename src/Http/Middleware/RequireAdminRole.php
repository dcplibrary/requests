<?php

namespace Dcplibrary\Requests\Http\Middleware;

use Closure;
use Dcplibrary\Requests\Models\User as StaffUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to active staff users with the 'admin' role.
 *
 * Applied (as `request.admin`) to every settings-area route: settings, backups,
 * users, groups, catalog labels, statuses and email templates.
 *
 * This middleware throws instead of returning a response so that it also works
 * as Livewire persistent middleware, where returned responses are discarded.
 */
class RequireAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $staffUser = StaffUser::forAuthenticatable($request->user());

        if (! $staffUser || ! $staffUser->active || ! $staffUser->isAdmin()) {
            abort(response()->view('requests::staff.no-access', [
                'appName' => config('app.name'),
            ], 403));
        }

        return $next($request);
    }
}
