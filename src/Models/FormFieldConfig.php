<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-form configuration for a field (visibility, order, required, step, etc.).
 *
 * Replaces FormFormField + FormCustomField.
 *
 * @property int         $id
 * @property int         $form_id
 * @property int         $field_id
 * @property string|null $label_override
 * @property int         $sort_order
 * @property bool        $required
 * @property bool        $visible
 * @property int         $step
 * @property array|null  $conditional_logic
 */
class FormFieldConfig extends Model
{
    /** @var string */
    protected $table = 'form_field_config';

    /** @var list<string> */
    protected $fillable = [
        'form_id',
        'field_id',
        'label_override',
        'sort_order',
        'required',
        'visible',
        'step',
        'conditional_logic',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'required'          => 'boolean',
        'visible'           => 'boolean',
        'step'              => 'integer',
        'sort_order'        => 'integer',
        'conditional_logic' => 'array',
    ];

    /** The form this config belongs to. */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** The field being configured. */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
