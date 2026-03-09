<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A type of library material a patron can request (e.g. Book, DVD, eBook).
 *
 * Material types appear as radio options on the patron form (Step 2). The
 * `has_other_text` flag enables a free-text input for the "Other" type.
 * Selector groups link users to the material types they are responsible for.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property bool        $active
 * @property bool        $ill_enabled     When true, show this type on the ILL form (configurable per type)
 * @property bool        $has_other_text  When true, show a free-text "please specify" input
 * @property int         $sort_order
 * @property int|null    $created_by
 * @property int|null    $modified_by
 * @property \Carbon\Carbon|null $deleted_at
 */
class MaterialType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'active', 'ill_enabled', 'has_other_text', 'sort_order', 'created_by', 'modified_by'];

    protected $casts = [
        'active'         => 'boolean',
        'ill_enabled'    => 'boolean',
        'has_other_text' => 'boolean',
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

    /** Materials catalogued under this type. */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /** Requests submitted for this material type. */
    public function requests(): HasMany
    {
        return $this->hasMany(SfpRequest::class);
    }

    /** Selector groups that cover this material type. */
    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(SelectorGroup::class, 'selector_group_material_type');
    }

    /** Form-specific configs that include this material type. */
    public function formMaterialTypes(): HasMany
    {
        return $this->hasMany(FormMaterialType::class);
    }

    /** Scope ordered by sort_order (all records, active or not). */
    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order');
    }

    /** Scope to active types, ordered by sort_order. */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }

    /** Scope to types available on the ILL form (active + ill_enabled). */
    public function scopeActiveForIll(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->where('ill_enabled', true)
                    ->orWhereNull('ill_enabled');
            })
            ->orderBy('sort_order');
    }
}
