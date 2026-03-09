<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-form override for one option on an option-type form field.
 *
 * Option-type form fields are those whose key is one of:
 *   material_type, audience, genre, console
 *
 * Each option (identified by its slug) can be hidden, reordered, or
 * given a form-specific label override without changing the global option.
 * Rows are created on first user action; absence means "use global defaults".
 *
 * @property int         $id
 * @property int         $form_id
 * @property int         $form_field_id
 * @property string      $option_slug      Slug of the underlying model row
 * @property string|null $label_override
 * @property int         $sort_order
 * @property bool        $visible
 */
class FormFormFieldOption extends Model
{
    protected $table = 'form_form_field_options';

    protected $fillable = [
        'form_id',
        'form_field_id',
        'option_slug',
        'label_override',
        'sort_order',
        'visible',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'visible'    => 'boolean',
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
