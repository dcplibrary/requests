<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named group that scopes selector users to specific field options.
 *
 * Selectors assigned to a group can only see requests whose field option values
 * (material type, audience, etc.) fall within that group's scope. A selector with
 * no group assignments sees no requests. Admins bypass group scoping entirely.
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

    /** Field options (material types, audiences, etc.) covered by this group. */
    public function fieldOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            FieldOption::class,
            'selector_group_field_option',
            'selector_group_id',
            'field_option_id'
        );
    }

    /**
     * Field options for a specific field key (e.g. 'material_type', 'audience').
     *
     * @param  string  $fieldKey
     * @return BelongsToMany
     */
    public function fieldOptionsForKey(string $fieldKey): BelongsToMany
    {
        return $this->fieldOptions()
            ->whereHas('field', fn ($q) => $q->where('key', $fieldKey));
    }

    /** Scope to active groups only. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true);
    }
}
