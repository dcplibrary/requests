<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A form definition (e.g. ILL, SFP). Presentation config—which fields and
 * options appear, and their label/order/required/visibility/step/conditional
 * logic—is stored in `form_field_config` and `form_field_option_overrides`.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 */
class Form extends Model
{
    protected $fillable = ['name', 'slug'];

    /**
     * Fields attached to this form with per-form config (sort, required, visible, etc.).
     *
     * @return BelongsToMany
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'form_field_config')
            ->withPivot('label_override', 'sort_order', 'required', 'visible', 'step', 'conditional_logic')
            ->orderByPivot('sort_order');
    }

    /** Per-form field configuration rows. */
    public function fieldConfigs(): HasMany
    {
        return $this->hasMany(FormFieldConfig::class)->orderBy('sort_order');
    }

    /** Per-form option overrides. */
    public function fieldOptionOverrides(): HasMany
    {
        return $this->hasMany(FormFieldOptionOverride::class);
    }

    /** Resolve form by slug (e.g. 'ill', 'sfp'). */
    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
