<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workflow status for patron requests (e.g. Pending, On Order, Purchased, Denied).
 *
 * `is_terminal` flags statuses that represent a resolved state — staff UIs can
 * use this to indicate a request requires no further action. Color is a hex
 * string used for status badges.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $color        Hex color code for status badge (e.g. '#72bf44')
 * @property string|null $icon     Heroicon outline name (e.g. 'clock', 'check-circle')
 * @property int    $sort_order
 * @property bool   $active
 * @property bool   $is_terminal  True for resolved statuses (Purchased, Denied, etc.)
 * @property bool   $notify_patron True if a status change to this status triggers a patron email
 * @property string|null $description Optional text describing the status for use in patron emails ({status_description})
 */
class RequestStatus extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'icon', 'sort_order', 'active', 'is_terminal', 'notify_patron', 'description'];

    protected $casts = [
        'active' => 'boolean',
        'is_terminal' => 'boolean',
        'notify_patron' => 'boolean',
    ];

    /** Requests currently in this status. */
    public function requests(): HasMany
    {
        return $this->hasMany(PatronRequest::class, 'request_status_id');
    }

    /** History entries that transitioned to this status. */
    public function history(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class);
    }

    /** Patron email templates that are sent when a request transitions to this status. */
    public function patronStatusTemplates(): BelongsToMany
    {
        return $this->belongsToMany(
            PatronStatusTemplate::class,
            'patron_status_template_request_status',
            'request_status_id',
            'patron_status_template_id'
        );
    }

    /** Scope to active statuses, ordered by sort_order. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
