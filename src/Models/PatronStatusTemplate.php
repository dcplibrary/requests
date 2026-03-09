<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A patron status email template. Each has its own subject and body and can be
 * linked to request statuses and optionally to material types. When a request
 * transitions to a status, matching templates (by status and optionally material
 * type) are sent. One template can be marked as default (fallback). Footer is universal.
 *
 * @property int    $id
 * @property string $name
 * @property bool   $enabled
 * @property string $subject
 * @property string|null $body
 * @property int    $sort_order
 * @property bool   $is_default
 */
class PatronStatusTemplate extends Model
{
    protected $fillable = ['name', 'enabled', 'subject', 'body', 'sort_order', 'is_default'];

    protected $casts = [
        'enabled' => 'boolean',
        'is_default' => 'boolean',
    ];

    /** Request statuses that trigger this template. */
    public function requestStatuses(): BelongsToMany
    {
        return $this->belongsToMany(
            RequestStatus::class,
            'patron_status_template_request_status',
            'patron_status_template_id',
            'request_status_id'
        );
    }

    /** Material types this template applies to; empty = all. */
    public function materialTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            MaterialType::class,
            'patron_status_template_material_type',
            'patron_status_template_id',
            'material_type_id'
        );
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
