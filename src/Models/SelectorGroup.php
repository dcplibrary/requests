<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named group that scopes selector users to specific material types and audiences.
 *
 * Selectors assigned to a group can only see requests whose material type and
 * audience both fall within that group's scope. A selector with no group
 * assignments sees no requests. Admins bypass group scoping entirely.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property bool        $active
 * @property string|null $notification_emails Comma/newline-separated list of routing email addresses
 */
class SelectorGroup extends Model
{
    protected $fillable = ['name', 'description', 'active', 'notification_emails'];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Staff users belonging to this group. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'selector_group_user');
    }

    /** Material types covered by this group. */
    public function materialTypes(): BelongsToMany
    {
        return $this->belongsToMany(MaterialType::class, 'selector_group_material_type');
    }

    /** Audiences covered by this group. */
    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(Audience::class, 'selector_group_audience');
    }

    /** Scope to active groups only. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true);
    }
}
