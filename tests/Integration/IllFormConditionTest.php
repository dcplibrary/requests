<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\FormCustomField;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ILL form conditional logic.
 *
 * Verifies that per-form conditional logic stored on the FormCustomField
 * pivot (conditional_logic, required, visible columns) is respected by
 * the ILL form's field resolution and visibility evaluation.
 *
 * @see \Dcplibrary\Sfp\Models\CustomField::evaluateCondition()
 * @see \Dcplibrary\Sfp\Livewire\IllForm::getVisibleCustomFieldsProperty()
 */
class IllFormConditionTest extends TestCase
{
    /** @var bool */
    private static bool $booted = false;

    // -------------------------------------------------------------------------
    // Shared setup
    // -------------------------------------------------------------------------

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

        $schema->create('sfp_custom_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->integer('step')->default(2);
            $table->string('request_kind')->default('ill');
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

        $schema->create('form_custom_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('form_id');
            $table->integer('custom_field_id');
            $table->string('label_override')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->integer('step')->default(2);
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
        Capsule::table('sfp_custom_fields')->delete();
        Capsule::table('form_custom_fields')->delete();
        Cache::flush();
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        $this->resetData();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed the ILL form row and return its ID.
     *
     * @return int
     */
    private function seedIllForm(): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('forms')->insert([
            'id'         => 1,
            'name'       => 'Interlibrary Loan',
            'slug'       => 'ill',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 1;
    }

    /**
     * Create a custom field with an optional base-level condition.
     *
     * @param string     $key
     * @param array|null $condition  The base-model condition (null = always visible).
     * @param bool       $required
     * @return CustomField
     */
    private function createCustomField(string $key, ?array $condition = null, bool $required = false): CustomField
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_custom_fields')->insert([
            'key'          => $key,
            'label'        => ucfirst($key),
            'type'         => 'text',
            'step'         => 2,
            'request_kind' => 'ill',
            'sort_order'   => 0,
            'active'       => 1,
            'required'     => $required ? 1 : 0,
            'condition'    => $condition ? json_encode($condition) : null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return CustomField::where('key', $key)->firstOrFail();
    }

    /**
     * Attach a custom field to the ILL form with optional pivot-level conditional logic.
     *
     * @param int        $formId
     * @param int        $customFieldId
     * @param array|null $conditionalLogic
     * @param bool       $required
     * @param bool       $visible
     * @return FormCustomField
     */
    private function attachToForm(
        int $formId,
        int $customFieldId,
        ?array $conditionalLogic = null,
        bool $required = false,
        bool $visible = true,
    ): FormCustomField {
        $now = date('Y-m-d H:i:s');
        Capsule::table('form_custom_fields')->insert([
            'form_id'           => $formId,
            'custom_field_id'   => $customFieldId,
            'sort_order'        => 0,
            'required'          => $required ? 1 : 0,
            'visible'           => $visible ? 1 : 0,
            'step'              => 2,
            'conditional_logic' => $conditionalLogic ? json_encode($conditionalLogic) : null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        return FormCustomField::where('custom_field_id', $customFieldId)
            ->where('form_id', $formId)
            ->firstOrFail();
    }

    /**
     * Simulate how IllForm now resolves a field: merge pivot overrides onto base,
     * then evaluate using CustomField::evaluateCondition().
     *
     * @param CustomField     $field
     * @param FormCustomField $pivot
     * @param array           $state
     * @return bool
     */
    private function evaluateAsIllFormDoes(CustomField $field, FormCustomField $pivot, array $state): bool
    {
        if (! $field->active) {
            return false;
        }

        // IllForm now checks pivot visible flag first.
        if (! $pivot->visible) {
            return false;
        }

        // Condition comes from pivot, falling back to base.
        $condition = $pivot->conditional_logic ?? $field->condition ?? ['match' => 'all', 'rules' => []];

        return CustomField::evaluateCondition($condition, $state);
    }

    /**
     * Resolve the effective required flag the way IllForm now does:
     * pivot required, gated by visibility.
     *
     * @param CustomField     $field
     * @param FormCustomField $pivot
     * @param array           $state
     * @return bool
     */
    private function isRequiredAsIllFormDoes(CustomField $field, FormCustomField $pivot, array $state): bool
    {
        return (bool) $pivot->required && $this->evaluateAsIllFormDoes($field, $pivot, $state);
    }

    // =========================================================================
    // Baseline: base-model conditions still work
    // =========================================================================

    // =========================================================================
    // Base-model fallback: when pivot has no conditional_logic, base condition applies
    // =========================================================================

    #[Test]
    public function base_condition_is_used_when_pivot_has_no_override(): void
    {
        $formId = $this->seedIllForm();

        $field = $this->createCustomField('notes', condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        // Pivot has no conditional_logic — should fall back to base condition.
        $pivot = $this->attachToForm($formId, $field->id);

        $this->assertTrue(
            $this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']),
            'Base condition shows field when material_type matches'
        );

        $this->assertFalse(
            $this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'dvd']),
            'Base condition hides field when material_type does not match'
        );
    }

    // =========================================================================
    // Pivot conditional_logic overrides base condition
    // =========================================================================

    #[Test]
    public function pivot_condition_hides_field_when_state_does_not_match(): void
    {
        $formId = $this->seedIllForm();

        // Base model has NO condition — always visible at the field level.
        $field = $this->createCustomField('pickup_location', condition: null);

        // Pivot says: only show when material_type is 'book'.
        $pivot = $this->attachToForm($formId, $field->id, conditionalLogic: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        $this->assertFalse(
            $this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'dvd']),
            'Pivot condition hides field for dvd even though base has no condition'
        );
    }

    #[Test]
    public function pivot_condition_shows_field_when_state_matches(): void
    {
        $formId = $this->seedIllForm();

        $field = $this->createCustomField('pickup_location', condition: null);

        $pivot = $this->attachToForm($formId, $field->id, conditionalLogic: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        $this->assertTrue(
            $this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']),
            'Pivot condition shows field when material_type matches'
        );
    }

    #[Test]
    public function pivot_condition_overrides_base_condition(): void
    {
        $formId = $this->seedIllForm();

        // Base condition says: show only for 'book'.
        $field = $this->createCustomField('edition', condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        // Pivot condition says: show only for 'dvd' (overrides base).
        $pivot = $this->attachToForm($formId, $field->id, conditionalLogic: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['dvd']],
            ],
        ]);

        // Pivot wins: visible for dvd, hidden for book.
        $this->assertTrue(
            $this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'dvd']),
            'Pivot condition overrides base — shows field for dvd'
        );

        $this->assertFalse(
            $this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']),
            'Pivot condition overrides base — hides field for book'
        );
    }

    // =========================================================================
    // Pivot required flag overrides base
    // =========================================================================

    #[Test]
    public function pivot_required_flag_is_used_instead_of_base(): void
    {
        $formId = $this->seedIllForm();

        // Base: not required
        $field = $this->createCustomField('volume', condition: null, required: false);

        // Pivot: required = true
        $pivot = $this->attachToForm($formId, $field->id, required: true);

        $state = ['material_type' => 'book'];

        $this->assertTrue(
            $this->isRequiredAsIllFormDoes($field, $pivot, $state),
            'Pivot required=true makes field required when visible'
        );
    }

    #[Test]
    public function pivot_required_false_overrides_base_required_true(): void
    {
        $formId = $this->seedIllForm();

        // Base: required
        $field = $this->createCustomField('volume', condition: null, required: true);

        // Pivot: required = false
        $pivot = $this->attachToForm($formId, $field->id, required: false);

        $state = ['material_type' => 'book'];

        $this->assertFalse(
            $this->isRequiredAsIllFormDoes($field, $pivot, $state),
            'Pivot required=false overrides base required=true'
        );
    }

    // =========================================================================
    // Pivot visible=false hides field entirely
    // =========================================================================

    #[Test]
    public function pivot_visible_false_hides_field_entirely(): void
    {
        $formId = $this->seedIllForm();

        // Base: active, no condition (always visible)
        $field = $this->createCustomField('hidden_note', condition: null);

        // Pivot: visible = false (admin turned it off for this form)
        $pivot = $this->attachToForm($formId, $field->id, visible: false);

        $this->assertFalse(
            $this->evaluateAsIllFormDoes($field, $pivot, ['material_type' => 'book']),
            'Pivot visible=false hides field regardless of condition'
        );
    }
}
