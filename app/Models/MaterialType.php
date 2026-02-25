<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MaterialType extends Model
{
    protected $fillable = ['name', 'slug', 'active', 'has_other_text', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
        'has_other_text' => 'boolean',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_material_type');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
