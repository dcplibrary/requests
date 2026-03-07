<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stored value for a CustomField on a specific request.
 *
 * @property int $id
 * @property int $request_id
 * @property int $custom_field_id
 * @property string|null $value_slug
 * @property string|null $value_text
 */
class RequestCustomFieldValue extends Model
{
    protected $table = 'sfp_request_custom_field_values';

    protected $fillable = ['request_id', 'custom_field_id', 'value_slug', 'value_text'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SfpRequest::class, 'request_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}

