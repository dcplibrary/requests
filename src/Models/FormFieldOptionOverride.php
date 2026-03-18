<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-form override for a field option (label, visibility, order).
 *
 * Replaces FormFormFieldOption + FormCustomFieldOption.
 *
 * @property int $id
 * @property int $form_id
 * @property int $field_id
 * @property string $option_slug
 * @property string|null $label_override
 * @property int $sort_order
 * @property bool $visible
 */
class FormFieldOptionOverride extends Model
{
    /** @var string */
    protected $table = 'form_field_option_overrides';

    /** @var list<string> */
    protected $fillable = [
        'form_id',
        'field_id',
        'option_slug',
        'label_override',
        'sort_order',
        'visible',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** The form this override belongs to. */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** The field whose option is being overridden. */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    /**
     * Create or update a per-form option row (sets timestamps on insert; avoids raw insert quirks on SQLite).
     *
     * @param  array<string, mixed>  $values  visible, sort_order, label_override, etc.
     */
    public static function upsertForForm(int $formId, int $fieldId, string $optionSlug, array $values): void
    {
        $model = static::firstOrNew([
            'form_id' => $formId,
            'field_id' => $fieldId,
            'option_slug' => $optionSlug,
        ]);

        $wasNew = ! $model->exists;

        foreach ($values as $key => $value) {
            $model->setAttribute($key, $value);
        }

        if ($wasNew) {
            if (! $model->isDirty('visible') && $model->getAttribute('visible') === null) {
                $model->setAttribute('visible', true);
            }
            if (! $model->isDirty('sort_order') && $model->getAttribute('sort_order') === null) {
                $model->setAttribute('sort_order', 0);
            }
        }

        /*
         * SQLite: some DBs have `id INTEGER NOT NULL` without INTEGER PRIMARY KEY, so omitting `id`
         * on insert fails. Assign the next id explicitly for new rows (SFP + ILL material toggles).
         */
        if ($wasNew && $model->getConnection()->getDriverName() === 'sqlite') {
            $max = static::query()->max('id');
            $model->setAttribute('id', ((int) $max) + 1);
        }

        $model->save();
    }
}
