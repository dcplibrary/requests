<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestStatus extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'sort_order', 'active', 'is_terminal'];

    protected $casts = [
        'active' => 'boolean',
        'is_terminal' => 'boolean',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(SfpRequest::class, 'request_status_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
