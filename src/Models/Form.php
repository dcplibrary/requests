<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A form definition (e.g. ILL, SFP). Presentation config—which material types,
 * custom fields, and options appear, and their label/order/required/visibility/
 * step/conditional_logic—is stored in pivot tables (form_material_types,
 * form_custom_fields, form_custom_field_options).
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 */
class Form extends Model
{
    protected $fillable = ['name', 'slug'];

    /** Material types attached to this form with per-form overrides. */
    public function formMaterialTypes(): HasMany
    {
        return $this->hasMany(FormMaterialType::class)->orderBy('sort_order');
    }

    /** Custom fields attached to this form with per-form overrides. */
    public function formCustomFields(): HasMany
    {
        return $this->hasMany(FormCustomField::class)->orderBy('sort_order');
    }

    /** Custom field option overrides for this form. */
    public function formCustomFieldOptions(): HasMany
    {
        return $this->hasMany(FormCustomFieldOption::class);
    }

    /** Resolve form by slug (e.g. 'ill', 'sfp'). */
    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
