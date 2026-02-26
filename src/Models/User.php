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
 * - `selector` — access scoped to their assigned SelectorGroups
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

    /** Selector groups this user belongs to. */
    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_user');
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
