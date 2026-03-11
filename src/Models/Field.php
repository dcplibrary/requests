<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unified field definition — replaces FormField + CustomField.
 *
 * Every field used in requests (material_type, audience, genre, title, author, etc.)
 * is a row in this table. Select/radio fields have options via {@see FieldOption}.
 *
 * Conditional rules shape:
 * { match: 'all'|'any', rules: [{ field: string, operator: 'in'|'not_in', values: list<string> }] }
 *
 * @property int              $id
 * @property string           $key              Unique machine key (e.g. 'material_type', 'audience')
 * @property string           $label            Default human label
 * @property array|null       $label_overrides  Context-specific label overrides (JSON)
 * @property string           $type             text|textarea|date|number|checkbox|select|radio
 * @property int              $step             Default form step (1-based)
 * @property string           $scope            both|sfp|ill
 * @property int              $sort_order
 * @property bool             $active
 * @property bool             $required
 * @property bool             $include_as_token
 * @property bool             $filterable
 * @property array|null       $condition        Conditional visibility rules (JSON)
 * @property string|null      $created_by
 * @property string|null      $modified_by
 * @property \Carbon\Carbon|null $deleted_at
 */
class Field extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'fields';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'label',
        'label_overrides',
        'type',
        'step',
        'scope',
        'sort_order',
        'active',
        'required',
        'include_as_token',
        'filterable',
        'condition',
        'created_by',
        'modified_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active'           => 'boolean',
        'required'         => 'boolean',
        'include_as_token' => 'boolean',
        'filterable'       => 'boolean',
        'condition'        => 'array',
        'label_overrides'  => 'array',
        'step'             => 'integer',
        'sort_order'       => 'integer',
    ];

    /**
     * Auto-set created_by / modified_by from the authenticated user.
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
        });
    }

    /** User who created this field (staff). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** User who last modified this field (staff). */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    /** Options for select/radio fields. */
    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class);
    }

    /** Request-level values stored for this field. */
    public function values(): HasMany
    {
        return $this->hasMany(RequestFieldValue::class);
    }

    /** Per-form configurations for this field. */
    public function formFieldConfigs(): HasMany
    {
        return $this->hasMany(\Dcplibrary\Requests\Models\FormFieldConfig::class ?? null, 'field_id');
    }

    /**
     * Scope to fields ordered by sort_order.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to active fields.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope to fields matching the given request kind (sfp, ill, or both).
     *
     * @param  Builder  $query
     * @param  string   $kind  sfp|ill
     * @return Builder
     */
    public function scopeForKind(Builder $query, string $kind): Builder
    {
        return $query->whereIn('scope', [$kind, 'both']);
    }

    /**
     * Evaluate a condition array against current form state.
     *
     * @param  array{match?: string, rules?: array}  $condition
     * @param  array<string, string|null>             $state  key => selected slug/string
     * @return bool
     */
    public static function evaluateCondition(array $condition, array $state): bool
    {
        if (empty($condition['rules'])) {
            return true;
        }

        $match = $condition['match'] ?? 'all';
        $rules = $condition['rules'] ?? [];

        $results = array_map(function (array $rule) use ($state) {
            $field    = $rule['field'] ?? '';
            $operator = $rule['operator'] ?? 'in';
            $values   = $rule['values'] ?? [];
            $current  = $state[$field] ?? null;

            if ($current === null || $current === '') {
                return false;
            }

            return match ($operator) {
                'in'     => in_array($current, $values, true),
                'not_in' => ! in_array($current, $values, true),
                default  => false,
            };
        }, $rules);

        return $match === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    /**
     * Whether this field should be visible given the current form state.
     *
     * @param  array<string, string|null>  $state  key => selected slug/string
     * @return bool
     */
    public function isVisibleFor(array $state): bool
    {
        if (! $this->active) {
            return false;
        }

        return static::evaluateCondition($this->condition ?? ['match' => 'all', 'rules' => []], $state);
    }

    /**
     * Whether this field is required given the current form state.
     *
     * @param  array<string, string|null>  $state
     * @return bool
     */
    public function isRequiredFor(array $state): bool
    {
        return (bool) $this->required && $this->isVisibleFor($state);
    }
}
