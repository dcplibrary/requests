<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A type of library material a patron can request (e.g. Book, DVD, eBook).
 *
 * Material types appear as radio options on the patron form (Step 2). The
 * `has_other_text` flag enables a free-text input for the "Other" type.
 * Selector groups link users to the material types they are responsible for.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property bool   $active
 * @property bool   $has_other_text  When true, show a free-text "please specify" input
 * @property int    $sort_order
 */
class MaterialType extends Model
{
    protected $fillable = ['name', 'slug', 'active', 'has_other_text', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
        'has_other_text' => 'boolean',
    ];

    /** Materials catalogued under this type. */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /** Requests submitted for this material type. */
    public function requests(): HasMany
    {
        return $this->hasMany(SfpRequest::class);
    }

    /** Selector groups that cover this material type. */
    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_material_type');
    }

    /** Scope to active types, ordered by sort_order. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
