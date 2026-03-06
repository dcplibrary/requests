<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A gaming console option for patron suggestions.
 *
 * Console slugs are stored in `requests.other_material_text` when a patron
 * picks a video game material type. Options are managed by admins so new
 * platforms (e.g. Switch 2) can be added without a code deployment.
 *
 * @property int    $id
 * @property string $name        Human-readable label shown on the patron form (e.g. "PlayStation 5")
 * @property string $slug        Stable identifier stored on requests (e.g. "playstation-5")
 * @property int    $sort_order
 * @property bool   $active
 */
class Console extends Model
{
    protected $table = 'sfp_consoles';

    protected $fillable = ['name', 'slug', 'sort_order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Scope to active consoles, ordered by sort_order. */
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
