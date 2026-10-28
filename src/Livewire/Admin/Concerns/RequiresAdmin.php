<?php

namespace Dcplibrary\Requests\Livewire\Admin\Concerns;

use Dcplibrary\Requests\Models\User as StaffUser;

/**
 * Livewire trait: allow only active admins to render or update the component.
 *
 * Livewire calls boot{TraitName}() on the initial render and on every
 * subsequent /livewire/update request, so this covers actions that never pass
 * through the page route's middleware.
 */
trait RequiresAdmin
{
    public function bootRequiresAdmin(): void
    {
        $staffUser = StaffUser::forAuthenticatable(auth()->user());

        if (! $staffUser || ! $staffUser->active || ! $staffUser->isAdmin()) {
            abort(403);
        }
    }
}
