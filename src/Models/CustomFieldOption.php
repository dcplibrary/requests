<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Option for a select/radio CustomField.
 *
 * @property int $id
 * @property int $custom_field_id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $active
 */
class CustomFieldOption extends Model
{
    protected $table = 'sfp_custom_field_options';

    protected $fillable = ['custom_field_id', 'name', 'slug', 'sort_order', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

