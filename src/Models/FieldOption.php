<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Unified option row for a select/radio field.
 *
 * Replaces CustomFieldOption, MaterialType, Audience, and Genre.
 * Type-specific attributes live in the `metadata` JSON column:
 *   material_type: {"ill_enabled": true, "isbndb_searchable": false, "has_other_text": false}
 *   audience:      {"bibliocommons_value": "adult"}
 *   genre/other:   null
 *
 * Dependency locking prevents deletion or deactivation when the option is
 * referenced by selector groups or request field values.
 *
 * @property int              $id
 * @property int              $field_id
 * @property string           $name
 * @property string           $slug
 * @property int              $sort_order
 * @property bool             $active
 * @property array|null       $metadata       Type-specific JSON attributes
 * @property string|null      $created_by
 * @property string|null      $modified_by
 * @property \Carbon\Carbon|null $deleted_at
 */
class FieldOption extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'field_options';

    /** @var list<string> */
    protected $fillable = [
        'field_id',
        'name',
        'slug',
        'sort_order',
        'active',
        'metadata',
        'created_by',
        'modified_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
        'metadata'   => 'array',
    ];

    /**
     * Auto-set created_by / modified_by and enforce dependency locking.
     *
     * @return void
     */
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

            // Block deactivation when referenced
            if ($model->isDirty('active') && ! $model->active) {
                $model->guardAgainstReferences('deactivate');
            }
        });

        static::deleting(function (self $model): void {
            $model->guardAgainstReferences('delete');
        });
    }

    /**
     * Throw if this option is referenced by selector groups or request field values.
     *
     * @param  string  $action  delete|deactivate
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function guardAgainstReferences(string $action): void
    {
        $groupNames = DB::table('selector_group_field_option')
            ->join('selector_groups', 'selector_groups.id', '=', 'selector_group_field_option.selector_group_id')
            ->where('selector_group_field_option.field_option_id', $this->id)
            ->pluck('selector_groups.name');

        if ($groupNames->isNotEmpty()) {
            throw new \RuntimeException(
                "Cannot {$action} — used by groups: " . $groupNames->implode(', ')
            );
        }

        $valueCount = DB::table('request_field_values')
            ->where('field_id', $this->field_id)
            ->where('value', $this->slug)
            ->count();

        if ($valueCount > 0) {
            throw new \RuntimeException(
                "Cannot {$action} — referenced by {$valueCount} request value(s)."
            );
        }
    }

    /** The field this option belongs to. */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
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

    /** Selector groups that reference this option. */
    public function selectorGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            SelectorGroup::class,
            'selector_group_field_option',
            'field_option_id',
            'selector_group_id'
        );
    }

    /**
     * Read a metadata key with an optional default.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function meta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Scope to options ordered by sort_order.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to active options.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
