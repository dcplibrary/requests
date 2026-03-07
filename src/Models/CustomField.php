<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-defined custom field usable on SFP/ILL patron forms.
 *
 * Conditional rules use the same shape as FormField:
 * { match: 'all'|'any', rules: [{ field: string, operator: 'in'|'not_in', values: list<string> }] }
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string $type
 * @property int $step
 * @property string $request_kind
 * @property int $sort_order
 * @property bool $active
 * @property bool $required
 * @property bool $include_as_token
 * @property bool $filterable
 * @property array|null $condition
 */
class CustomField extends Model
{
    protected $table = 'sfp_custom_fields';

    protected $fillable = [
        'key',
        'label',
        'type',
        'step',
        'request_kind',
        'sort_order',
        'active',
        'required',
        'include_as_token',
        'filterable',
        'condition',
    ];

    protected $casts = [
        'active'           => 'boolean',
        'required'         => 'boolean',
        'include_as_token' => 'boolean',
        'filterable'       => 'boolean',
        'condition'        => 'array',
        'step'             => 'integer',
        'sort_order'       => 'integer',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(CustomFieldOption::class, 'custom_field_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(RequestCustomFieldValue::class, 'custom_field_id');
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
     * @param array<string, string|null> $state key => selected slug/string
     */
    public function isVisibleFor(array $state): bool
    {
        if (! $this->active) {
            return false;
        }

        if (empty($this->condition['rules'])) {
            return true;
        }

        $match = $this->condition['match'] ?? 'all';
        $rules = $this->condition['rules'] ?? [];

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
     * @param array<string, string|null> $state
     */
    public function isRequiredFor(array $state): bool
    {
        return (bool) $this->required && $this->isVisibleFor($state);
    }
}

