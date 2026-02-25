<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'entra_id', 'role', 'active', 'last_login_at'];

    protected $hidden = ['remember_token'];

    protected $casts = [
        'active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSelector(): bool
    {
        return $this->role === 'selector';
    }

    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_user');
    }

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
