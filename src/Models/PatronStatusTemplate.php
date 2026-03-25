<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A patron status email template. Each has its own subject and body and can be
 * linked to request statuses and optionally to material types. When a request
 * transitions to a status, matching templates (by status and optionally material
 * type) are sent. Templates may also fire when an SFP request is converted to ILL
 * ({@see $trigger_on_ill_conversion}) because conversion does not change status id.
 * One template can be marked as default (fallback). Footer is universal.
 *
 * @property int    $id
 * @property string $name
 * @property bool   $enabled
 * @property string $subject
 * @property string|null $body
 * @property int    $sort_order
 * @property bool   $is_default
 * @property bool   $trigger_on_ill_conversion  When true, send when SFP → ILL (staff convert or signed link), not only on status change
 */
class PatronStatusTemplate extends Model
{
    protected $fillable = ['name', 'enabled', 'subject', 'body', 'sort_order', 'is_default', 'trigger_on_ill_conversion'];

    protected $casts = [
        'enabled' => 'boolean',
        'is_default' => 'boolean',
        'trigger_on_ill_conversion' => 'boolean',
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

    /** Field options (e.g. material types) this template applies to; empty = all. */
    public function fieldOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            FieldOption::class,
            'patron_status_template_field_option',
            'patron_status_template_id',
            'field_option_id'
        );
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
