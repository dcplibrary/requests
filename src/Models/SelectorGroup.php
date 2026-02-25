<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SelectorGroup extends Model
{
    protected $fillable = ['name', 'description', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'selector_group_user');
    }

    public function materialTypes(): BelongsToMany
    {
        return $this->belongsToMany(MaterialType::class, 'selector_group_material_type');
    }

    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(Audience::class, 'selector_group_audience');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true);
    }
}
