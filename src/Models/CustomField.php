<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Admin-defined custom field usable on SFP/ILL patron forms.
 *
 * Conditional rules use the same shape as FormField:
 * { match: 'all'|'any', rules: [{ field: string, operator: 'in'|'not_in', values: list<string> }] }
 *
 * @property int         $id
 * @property string      $key
 * @property string      $label
 * @property string      $type
 * @property int         $step
 * @property string      $request_kind
 * @property int         $sort_order
 * @property bool        $active
 * @property bool        $required
 * @property bool        $include_as_token
 * @property bool        $filterable
 * @property array|null  $condition
 * @property array|null  $label_overrides  Optional map of context (e.g. material_type slug) => label for this field
 * @property int|null    $created_by
 * @property int|null    $modified_by
 * @property \Carbon\Carbon|null $deleted_at
 */
class CustomField extends Model
{
    use SoftDeletes;

    protected $table = 'sfp_custom_fields';

    protected $fillable = [
        'key',
        'label',
        'label_overrides',
        'type',
        'step',
        'request_kind',
        'sort_order',
        'active',
        'required',
        'include_as_token',
        'filterable',
        'condition',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'active'           => 'boolean',
        'required'         => 'boolean',
        'include_as_token' => 'boolean',
        'filterable'       => 'boolean',
        'condition'        => 'array',
        'label_overrides'   => 'array',
        'step'             => 'integer',
        'sort_order'       => 'integer',
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

    public function options(): HasMany
    {
        return $this->hasMany(CustomFieldOption::class, 'custom_field_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(RequestCustomFieldValue::class, 'custom_field_id');
    }

    /** Form-specific configs that include this custom field. */
    public function formCustomFields(): HasMany
    {
        return $this->hasMany(FormCustomField::class, 'custom_field_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForKind(Builder $query, string $kind): Builder
    {
        return $query->whereIn('request_kind', [$kind, 'both']);
    }

    /**
     * Evaluate a condition array against current state (for form-specific conditional_logic).
     *
     * @param  array<string, string|null>  $state  key => selected slug/string
     * @param  array{match?: string, rules?: array}  $condition  Same shape as condition/conditional_logic
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
     * @param array<string, string|null> $state key => selected slug/string
     */
    public function isVisibleFor(array $state): bool
    {
        if (! $this->active) {
            return false;
        }

        return static::evaluateCondition($this->condition ?? ['match' => 'all', 'rules' => []], $state);
    }

    /**
     * @param array<string, string|null> $state
     */
    public function isRequiredFor(array $state): bool
    {
        return (bool) $this->required && $this->isVisibleFor($state);
    }
}

