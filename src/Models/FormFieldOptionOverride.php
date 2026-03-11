<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-form override for a field option (label, visibility, order).
 *
 * Replaces FormFormFieldOption + FormCustomFieldOption.
 *
 * @property int         $id
 * @property int         $form_id
 * @property int         $field_id
 * @property string      $option_slug
 * @property string|null $label_override
 * @property int         $sort_order
 * @property bool        $visible
 */
class FormFieldOptionOverride extends Model
{
    /** @var string */
    protected $table = 'form_field_option_overrides';

    /** @var list<string> */
    protected $fillable = [
        'form_id',
        'field_id',
        'option_slug',
        'label_override',
        'sort_order',
        'visible',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'visible'    => 'boolean',
        'sort_order' => 'integer',
    ];

    /** The form this override belongs to. */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** The field whose option is being overridden. */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
