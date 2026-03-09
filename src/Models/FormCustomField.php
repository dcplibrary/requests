<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot: which custom fields appear on a form, with per-form label, order,
 * required, visibility, step, and conditional logic.
 *
 * @property int         $id
 * @property int         $form_id
 * @property int         $custom_field_id
 * @property string|null  $label_override
 * @property int         $sort_order
 * @property bool        $required
 * @property bool        $visible
 * @property int         $step
 * @property array|null  $conditional_logic
 */
class FormCustomField extends Model
{
    protected $table = 'form_custom_fields';

    protected $fillable = [
        'form_id',
        'custom_field_id',
        'label_override',
        'sort_order',
        'required',
        'visible',
        'step',
        'conditional_logic',
    ];

    protected $casts = [
        'required'          => 'boolean',
        'visible'            => 'boolean',
        'sort_order'         => 'integer',
        'step'               => 'integer',
        'conditional_logic'  => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}
