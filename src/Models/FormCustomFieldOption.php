<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot: per-form overrides for a custom field option (label, order, visibility,
 * conditional logic). When absent, the option uses the base CustomFieldOption.
 *
 * @property int         $id
 * @property int         $form_id
 * @property int         $custom_field_option_id
 * @property string|null  $label_override
 * @property int         $sort_order
 * @property bool        $visible
 * @property array|null  $conditional_logic
 */
class FormCustomFieldOption extends Model
{
    protected $table = 'form_custom_field_options';

    protected $fillable = [
        'form_id',
        'custom_field_option_id',
        'label_override',
        'sort_order',
        'visible',
        'conditional_logic',
    ];

    protected $casts = [
        'visible'            => 'boolean',
        'sort_order'         => 'integer',
        'conditional_logic'  => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function customFieldOption(): BelongsTo
    {
        return $this->belongsTo(CustomFieldOption::class, 'custom_field_option_id');
    }
}
