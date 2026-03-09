<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot: which form fields (sfp_form_fields) appear on a form (SFP or ILL),
 * with per-form sort_order, conditional_logic, required, and visible.
 *
 * @property int         $id
 * @property int         $form_id
 * @property int         $form_field_id
 * @property string|null $label_override  Per-form label; null = use base field label
 * @property int         $sort_order
 * @property array|null  $conditional_logic
 * @property bool        $required
 * @property bool        $visible
 */
class FormFormField extends Model
{
    protected $table = 'form_form_fields';

    protected $fillable = [
        'form_id',
        'form_field_id',
        'label_override',
        'sort_order',
        'conditional_logic',
        'required',
        'visible',
    ];

    protected $casts = [
        'sort_order'         => 'integer',
        'required'           => 'boolean',
        'visible'            => 'boolean',
        'conditional_logic'  => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }
}
