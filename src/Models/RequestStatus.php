<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workflow status for SFP requests (e.g. Pending, On Order, Purchased, Denied).
 *
 * `is_terminal` flags statuses that represent a resolved state — staff UIs can
 * use this to indicate a request requires no further action. Color is a hex
 * string used for status badges.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $color        Hex color code for status badge (e.g. '#72bf44')
 * @property int    $sort_order
 * @property bool   $active
 * @property bool   $is_terminal  True for resolved statuses (Purchased, Denied, etc.)
 */
class RequestStatus extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'sort_order', 'active', 'is_terminal'];

    protected $casts = [
        'active' => 'boolean',
        'is_terminal' => 'boolean',
    ];

    /** Requests currently in this status. */
    public function requests(): HasMany
    {
        return $this->hasMany(SfpRequest::class, 'request_status_id');
    }

    /** History entries that transitioned to this status. */
    public function history(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class);
    }

    /** Scope to active statuses, ordered by sort_order. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
