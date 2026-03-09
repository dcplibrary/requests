<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
 * @property bool        $include_as_token When true, expose {key} in notifications/templates
 * @property string      $form_scope      'global' (both forms), 'sfp', or 'ill'
 * @property int|null    $created_by
 * @property int|null    $modified_by
 * @property \Carbon\Carbon|null $deleted_at
 */
class FormField extends Model
{
    use SoftDeletes;

    protected $table = 'sfp_form_fields';

    protected $fillable = ['key', 'label', 'sort_order', 'active', 'required', 'condition', 'include_as_token', 'form_scope', 'created_by', 'modified_by'];

    protected $casts = [
        'active'    => 'boolean',
        'required'  => 'boolean',
        'condition' => 'array',
        'include_as_token' => 'boolean',
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

    /** Pivot rows attaching this field to forms (SFP / ILL) with per-form order and condition. */
    public function formFormFields(): HasMany
    {
        return $this->hasMany(FormFormField::class, 'form_field_id');
    }

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
            return static::where('include_as_token', true)->ordered()->get();
        });
    }

    public static function bustCache(): void
    {
        cache()->forget('sfp_form_fields');
        cache()->forget('sfp_form_fields_token');
    }
}
