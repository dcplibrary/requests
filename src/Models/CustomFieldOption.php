<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Option for a select/radio CustomField.
 *
 * @property int         $id
 * @property int         $custom_field_id
 * @property string      $name
 * @property string      $slug
 * @property int         $sort_order
 * @property bool        $active
 * @property int|null    $created_by
 * @property int|null    $modified_by
 * @property \Carbon\Carbon|null $deleted_at
 */
class CustomFieldOption extends Model
{
    use SoftDeletes;

    protected $table = 'sfp_custom_field_options';

    protected $fillable = ['custom_field_id', 'name', 'slug', 'sort_order', 'active', 'created_by', 'modified_by'];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->created_by === null && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
        static::updating(function (self $model): void {
            if (auth()->check()) {
                $model->modified_by = auth()->id();
            }
        });
    }

    /** User who created this record (staff). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** User who last modified this record (staff). */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    /** Form-specific overrides for this option. */
    public function formCustomFieldOptions(): HasMany
    {
        return $this->hasMany(FormCustomFieldOption::class, 'custom_field_option_id');
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

