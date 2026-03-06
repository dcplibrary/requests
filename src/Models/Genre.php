<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A fiction/nonfiction genre classification for patron suggestions.
 *
 * Genre slugs are referenced in FormField conditional logic rules and in
 * selector group scoping — renaming a slug will break existing conditions.
 *
 * @property int    $id
 * @property string $name        Human-readable label shown on the patron form
 * @property string $slug        Stable identifier used in conditions and stored on requests
 * @property int    $sort_order
 * @property bool   $active
 */
class Genre extends Model
{
    protected $table = 'sfp_genres';

    protected $fillable = ['name', 'slug', 'sort_order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Selector groups scoped to this genre. */
    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_genre');
    }

    /** Scope to active genres, ordered by sort_order. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }

    /** Scope ordered by sort_order. */
    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order');
    }
}
