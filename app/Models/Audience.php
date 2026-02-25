<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Audience extends Model
{
    protected $fillable = ['name', 'slug', 'bibliocommons_value', 'active', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_audience');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
