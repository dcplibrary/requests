<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ILL form conditional logic using the unified Field model.
 *
 * Verifies that per-form conditional logic stored on FormFieldConfig
 * (conditional_logic, required, visible columns) is respected by
 * the ILL form's field resolution and visibility evaluation.
 *
 * @see \Dcplibrary\Requests\Models\Field::evaluateCondition()
 * @see \Dcplibrary\Requests\Livewire\IllForm::getVisibleCustomFieldsProperty()
 */
class IllFormConditionTest extends TestCase
{
    /** @var bool */
    private static bool $booted = false;

    /**
     * Boot the in-memory SQLite database and create all required tables.
     *
     * @return void
     */
    private function bootDatabase(): void
    {
        if (self::$booted) {
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->schema();

        $schema->create('forms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $schema->create('fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->integer('step')->default(2);
            $table->string('scope')->default('ill');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('required')->default(false);
            $table->boolean('include_as_token')->default(false);
            $table->boolean('filterable')->default(false);
            $table->text('condition')->nullable();
            $table->text('label_overrides')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('modified_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('form_field_config', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('form_id');
            $table->integer('field_id');
            $table->string('label_override')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->text('conditional_logic')->nullable();
            $table->timestamps();
        });

        self::$booted = true;
    }

    /**
     * Truncate all tables and flush the cache between tests.
     *
     * @return void
     */
    private function resetData(): void
    {
        Capsule::table('forms')->delete();
        Capsule::table('fields')->delete();
        Capsule::table('form_field_config')->delete();
        Cache::flush();
    }

    /** {@inheritdoc} */
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        $this->resetData();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Seed the ILL form row and return its ID.
     *
     * @return int
     */
    private function seedIllForm(): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('forms')->insert([
            'id' => 1, 'name' => 'Interlibrary Loan', 'slug' => 'ill',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return 1;
    }

    /**
     * Create a field with an optional base-level condition.
     *
     * @param  string      $key
     * @param  array|null  $condition
     * @param  bool        $required
     * @return Field
     */
    private function createField(string $key, ?array $condition = null, bool $required = false): Field
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('fields')->insert([
            'key' => $key, 'label' => ucfirst($key), 'type' => 'text',
            'step' => 2, 'scope' => 'ill', 'sort_order' => 0,
            'active' => 1, 'required' => $required ? 1 : 0,
            'condition' => $condition ? json_encode($condition) : null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return Field::where('key', $key)->firstOrFail();
    }

    /**
     * Attach a field to the ILL form via FormFieldConfig.
     *
     * @param  int         $formId
     * @param  int         $fieldId
     * @param  array|null  $conditionalLogic
     * @param  bool        $required
     * @param  bool        $visible
     * @return FormFieldConfig
     */
    private function attachToForm(
        int $formId,
        int $fieldId,
        ?array $conditionalLogic = null,
        bool $required = false,
        bool $visible = true,
    ): FormFieldConfig {
        $now = date('Y-m-d H:i:s');
        Capsule::table('form_field_config')->insert([
            'form_id' => $formId, 'field_id' => $fieldId,
            'sort_order' => 0, 'required' => $required ? 1 : 0,
            'visible' => $visible ? 1 : 0,
            'conditional_logic' => $conditionalLogic ? json_encode($conditionalLogic) : null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return FormFieldConfig::where('field_id', $fieldId)
            ->where('form_id', $formId)
            ->firstOrFail();
    }

    /**
     * Simulate how IllForm resolves visibility: pivot visible → condition eval.
     *
     * @param  Field            $field
     * @param  FormFieldConfig  $pivot
     * @param  array            $state
     * @return bool
     */
    private function evaluateAsIllFormDoes(Field $field, FormFieldConfig $pivot, array $state): bool
    {
        if (! $field->active) {
            return false;
        }
        if (! $pivot->visible) {
            return false;
        }
        $condition = $pivot->conditional_logic ?? $field->condition ?? ['match' => 'all', 'rules' => []];

        return Field::evaluateCondition($condition, $state);
    }

    /**
     * Resolve the effective required flag.
     *
     * @param  Field            $field
     * @param  FormFieldConfig  $pivot
     * @param  array            $state
     * @return bool
     */
    private function isRequiredAsIllFormDoes(Field $field, FormFieldConfig $pivot, array $state): bool
    {
        return (bool) $pivot->required && $this->evaluateAsIllFormDoes($field, $pivot, $state);
    }

    // ── Base-model fallback ─────────────────────────────────────────────────

    #[Test]
    public function base_condition_is_used_when_pivot_has_no_override(): void
    {
        $formId = $this->seedIllForm();
        $field  = $this->createField('notes', condition: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['book']]],
        ]);
        $pivot = $this->attachToForm($formId, $field->id);

        $this->assertTrue($this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']));
        $this->assertFalse($this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'dvd']));
    }

    // ── Pivot conditional_logic overrides base ──────────────────────────────

    #[Test]
    public function pivot_condition_hides_field_when_state_does_not_match(): void
    {
        $formId = $this->seedIllForm();
        $field  = $this->createField('pickup_location');
        $pivot  = $this->attachToForm($formId, $field->id, conditionalLogic: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['book']]],
        ]);

        $this->assertFalse($this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'dvd']));
    }

    #[Test]
    public function pivot_condition_shows_field_when_state_matches(): void
    {
        $formId = $this->seedIllForm();
        $field  = $this->createField('pickup_location');
        $pivot  = $this->attachToForm($formId, $field->id, conditionalLogic: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['book']]],
        ]);

        $this->assertTrue($this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']));
    }

    #[Test]
    public function pivot_condition_overrides_base_condition(): void
    {
        $formId = $this->seedIllForm();
        $field  = $this->createField('edition', condition: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['book']]],
        ]);
        $pivot = $this->attachToForm($formId, $field->id, conditionalLogic: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['dvd']]],
        ]);

        $this->assertTrue($this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'dvd']));
        $this->assertFalse($this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']));
    }

    // ── Pivot required flag ─────────────────────────────────────────────────

    #[Test]
    public function pivot_required_flag_is_used_instead_of_base(): void
    {
        $formId = $this->seedIllForm();
        $field  = $this->createField('volume', required: false);
        $pivot  = $this->attachToForm($formId, $field->id, required: true);

        $this->assertTrue($this->isRequiredAsIllFormDoes($field, $pivot, ['material_type' => 'book']));
    }

    #[Test]
    public function pivot_required_false_overrides_base_required_true(): void
    {
        $formId = $this->seedIllForm();
        $field  = $this->createField('volume', required: true);
        $pivot  = $this->attachToForm($formId, $field->id, required: false);

        $this->assertFalse($this->isRequiredAsIllFormDoes($field, $pivot, ['material_type' => 'book']));
    }

    // ── Pivot visible=false ─────────────────────────────────────────────────

    #[Test]
    public function pivot_visible_false_hides_field_entirely(): void
    {
        $formId = $this->seedIllForm();
        $field  = $this->createField('hidden_note');
        $pivot  = $this->attachToForm($formId, $field->id, visible: false);

        $this->assertFalse($this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']));
    }
}
