<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Staff user account for the admin interface.
 *
 * Stored in the `staff_users` table (separate from the host application's users).
 * Authenticated via Azure Entra ID (OIDC). Role controls what data a user can see:
 * - `admin`    — full access to all requests, patrons, titles, and settings
 * - `selector` — access scoped to their assigned SelectorGroups (field options)
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
    protected $table = 'staff_users';

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
     * Get the field option IDs this user can access via their selector groups.
     * Optionally filtered by field key (e.g. 'material_type', 'audience').
     * Admins can see all.
     *
     * @param  string|null  $fieldKey  Limit to options belonging to this field key.
     * @return array<int>
     */
    public function accessibleFieldOptionIds(?string $fieldKey = null): array
    {
        $baseQuery = FieldOption::query();

        if ($fieldKey !== null) {
            $baseQuery->whereHas('field', fn ($q) => $q->where('key', $fieldKey));
        }

        if ($this->isAdmin()) {
            return $baseQuery->pluck('id')->toArray();
        }

        return $this->selectorGroups()
            ->with('fieldOptions')
            ->get()
            ->flatMap(function ($group) use ($fieldKey) {
                $options = $group->fieldOptions;
                if ($fieldKey !== null) {
                    $options = $options->filter(
                        fn (FieldOption $opt) => $opt->field && $opt->field->key === $fieldKey
                    );
                }
                return $options->pluck('id');
            })
            ->unique()
            ->values()
            ->toArray();
    }
}
