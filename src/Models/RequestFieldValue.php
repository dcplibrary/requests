<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stored value for a {@see Field} on a specific request (EAV).
 *
 * Replaces RequestCustomFieldValue. Also stores values that previously lived as
 * dedicated columns on the requests table (material_type, audience, genre, etc.).
 *
 * @property int         $id
 * @property int         $request_id
 * @property int         $field_id
 * @property string|null $value
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class RequestFieldValue extends Model
{
    /** @var string */
    protected $table = 'request_field_values';

    /** @var list<string> */
    protected $fillable = ['request_id', 'field_id', 'value'];

    /** The request this value belongs to. */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PatronRequest::class, 'request_id');
    }

    /** The field definition for this value. */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
