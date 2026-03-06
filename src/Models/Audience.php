<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Patron audience segment (Adult, Young Adult, Children).
 *
 * The `bibliocommons_value` is passed directly to the BiblioCommons search API
 * `audience:` filter field (e.g. "adult", "teen", "children").
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $bibliocommons_value  Value used in the BiblioCommons `audience:` filter
 * @property bool   $active
 * @property int    $sort_order
 */
class Audience extends Model
{
    protected $fillable = ['name', 'slug', 'bibliocommons_value', 'active', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Requests submitted for this audience. */
    public function requests(): HasMany
    {
        return $this->hasMany(SfpRequest::class);
    }

    /** Selector groups that cover this audience. */
    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_audience');
    }

    /** Scope ordered by sort_order (all records, active or not). */
    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order');
    }

    /** Scope to active audiences, ordered by sort_order. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
