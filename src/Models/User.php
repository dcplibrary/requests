<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Staff user account for the SFP admin interface.
 *
 * Stored in the `sfp_users` table (separate from the host application's users).
 * Authenticated via Azure Entra ID (OIDC). Role controls what data a user can see:
 * - `admin`    — full access to all requests, patrons, titles, and settings
 * - `selector` — access scoped to their assigned SelectorGroups (material types, audiences)
 *
 * ILL access is not a role; it is determined by group membership. The setting
 * `ill_selector_group_id` points to the selector group that may view and work
 * ILL requests. Any user in that group (whatever it is named, e.g. "ILL" or
 * "Cathats") has ILL access. Use hasIllAccess() for that check.
 *
 * @property int              $id
 * @property string           $name
 * @property string           $email
 * @property string|null      $entra_id      Azure Entra object ID
 * @property string           $role          'admin'|'selector'
 * @property bool             $active
 * @property \Carbon\Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    protected $table = 'sfp_users';

    protected $fillable = ['name', 'email', 'entra_id', 'role', 'active', 'last_login_at'];

    protected $hidden = ['remember_token'];

    protected $casts = [
        'active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /** Returns true when this user has the 'admin' role. */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Returns true when this user has the 'selector' role. */
    public function isSelector(): bool
    {
        return $this->role === 'selector';
    }

    /**
     * Returns true when this user can access the ILL queue and ILL workflow.
     * ILL access is group-based: admins always have it; otherwise the user must
     * be in the selector group whose ID is ill_selector_group_id (that group
     * can be named anything, e.g. "ILL" or "Cathats").
     */
    public function hasIllAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        $illGroupId = (int) Setting::get('ill_selector_group_id', 0);

        return $illGroupId > 0 && $this->inSelectorGroup($illGroupId);
    }

    /** Selector groups this user belongs to. */
    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_user');
    }

    public function inSelectorGroup(int $groupId): bool
    {
        return $this->selectorGroups()->whereKey($groupId)->exists();
    }

    /** Status history entries recorded by this user. */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class);
    }

    /**
     * Get the material type IDs this user can see via their selector groups.
     * Admins can see all.
     */
    public function accessibleMaterialTypeIds(): array
    {
        if ($this->isAdmin()) {
            return MaterialType::pluck('id')->toArray();
        }

        return $this->selectorGroups()
            ->with('materialTypes')
            ->get()
            ->flatMap(fn ($group) => $group->materialTypes->pluck('id'))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get the audience IDs this user can see via their selector groups.
     * Admins can see all.
     */
    public function accessibleAudienceIds(): array
    {
        if ($this->isAdmin()) {
            return Audience::pluck('id')->toArray();
        }

        return $this->selectorGroups()
            ->with('audiences')
            ->get()
            ->flatMap(fn ($group) => $group->audiences->pluck('id'))
            ->unique()
            ->values()
            ->toArray();
    }
}
