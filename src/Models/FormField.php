<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable patron-form field definition.
 *
 * Each row represents one of the fixed fields in Step 2 of the suggestion form.
 * Admins can change the render order, toggle visibility, and define conditional
 * logic that determines when the field appears based on the patron's current
 * selections (material_type slug, audience slug).
 *
 * @property int         $id
 * @property string      $key              Unique field identifier (e.g. 'genre', 'console')
 * @property string      $label            Human-readable label used in the admin UI
 * @property int         $sort_order
 * @property bool        $active           When false the field is never rendered
 * @property bool        $required         When true, validation fails if the field is blank
 * @property array|null  $condition        Conditional logic rules (null = always show)
 */
class FormField extends Model
{
    protected $table = 'sfp_form_fields';

    protected $fillable = ['key', 'label', 'sort_order', 'active', 'required', 'condition'];

    protected $casts = [
        'active'    => 'boolean',
        'required'  => 'boolean',
        'condition' => 'array',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    // ── Condition evaluation ─────────────────────────────────────────────────

    /**
     * Determine whether this field should be visible given the current form state.
     *
     * @param  array{material_type: string|null, audience: string|null}  $state
     *         Keys are field names, values are the currently selected slug.
     */
    public function isVisibleFor(array $state): bool
    {
        if (! $this->active) {
            return false;
        }

        if (empty($this->condition['rules'])) {
            return true; // No condition = always show
        }

        $match = $this->condition['match'] ?? 'all';
        $rules = $this->condition['rules'];

        $results = array_map(fn (array $rule) => $this->evaluateRule($rule, $state), $rules);

        return $match === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    /**
     * Determine whether this field is required for the given state.
     *
     * A field can only be required if it is currently visible.
     *
     * @param array{material_type: string|null, audience: string|null} $state
     */
    public function isRequiredFor(array $state): bool
    {
        return (bool) $this->required && $this->isVisibleFor($state);
    }

    private function evaluateRule(array $rule, array $state): bool
    {
        $field    = $rule['field']    ?? '';
        $operator = $rule['operator'] ?? 'in';
        $values   = $rule['values']   ?? [];

        $current = $state[$field] ?? null;

        if ($current === null || $current === '') {
            return false;
        }

        return match ($operator) {
            'in'     => in_array($current, $values, true),
            'not_in' => ! in_array($current, $values, true),
            default  => false,
        };
    }

    // ── Cache helpers ────────────────────────────────────────────────────────

    /**
     * Return all active fields ordered, from cache.
     * Cache is busted by FormFields admin component on every save.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function allOrdered()
    {
        return cache()->remember('sfp_form_fields', now()->addHour(), function () {
            return static::ordered()->get();
        });
    }

    /**
     * Return form fields whose submitted values are available as {key} tokens
     * in notification email templates.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function tokenFields()
    {
        return cache()->remember('sfp_form_fields_token', now()->addHour(), function () {
            return static::whereIn('key', [
                'genre', 'console', 'isbn', 'publish_date', 'where_heard', 'ill_requested',
            ])->ordered()->get();
        });
    }

    public static function bustCache(): void
    {
        cache()->forget('sfp_form_fields');
        cache()->forget('sfp_form_fields_token');
    }
}
