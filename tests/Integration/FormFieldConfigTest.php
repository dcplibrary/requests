<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\RequestFieldValue;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\PatronRequest;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for per-form field configuration, conditional logic,
 * and the unified model helpers introduced by the table consolidation.
 *
 * Covers:
 *  - form_field_config sort_order, visible, required, label_override
 *  - Per-form conditional_logic overriding base field condition
 *  - PatronRequest::fieldValueLabel() EAV resolution
 *  - SelectorGroup::fieldOptions() unified pivot
 *
 * Uses an in-memory SQLite database — no external services required.
 *
 * @see \Dcplibrary\Requests\Models\Field
 * @see \Dcplibrary\Requests\Models\FormFieldConfig
 * @see \Dcplibrary\Requests\Models\PatronRequest::fieldValueLabel()
 * @see \Dcplibrary\Requests\Models\SelectorGroup::fieldOptions()
 */
class FormFieldConfigTest extends TestCase
{
    /** @var bool */
    private static bool $booted = false;

    /** @var int */
    private static int $mtFieldId;

    /** @var int */
    private static int $audFieldId;

    // ── Bootstrap ────────────────────────────────────────────────────────────

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
            $table->string('scope')->default('both');
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

        $schema->create('field_options', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('field_id');
            $table->string('name');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->text('metadata')->nullable();
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
            $table->integer('step')->default(2);
            $table->text('conditional_logic')->nullable();
            $table->timestamps();
        });

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('request_kind')->default('sfp');
            $table->integer('assigned_to_user_id')->nullable();
            $table->timestamps();
        });

        $schema->create('request_field_values', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('request_id');
            $table->integer('field_id');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'field_id']);
        });

        $schema->create('selector_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $schema->create('selector_group_field_option', function (Blueprint $table) {
            $table->integer('selector_group_id');
            $table->integer('field_option_id');
        });

        $schema->create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->nullable();
            $table->text('description')->nullable();
            $table->text('tokens')->nullable();
            $table->timestamps();
        });

        // Seed lookup data: material_type + audience fields and options.
        $now = date('Y-m-d H:i:s');

        Capsule::table('fields')->insert([
            ['key' => 'material_type', 'label' => 'Material Type', 'type' => 'select', 'scope' => 'both', 'sort_order' => 10, 'active' => 1, 'required' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'audience',      'label' => 'Audience',      'type' => 'select', 'scope' => 'both', 'sort_order' => 20, 'active' => 1, 'required' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        self::$mtFieldId  = (int) Capsule::table('fields')->where('key', 'material_type')->value('id');
        self::$audFieldId = (int) Capsule::table('fields')->where('key', 'audience')->value('id');

        Capsule::table('field_options')->insert([
            ['field_id' => self::$mtFieldId,  'name' => 'Book',  'slug' => 'book',  'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => self::$mtFieldId,  'name' => 'DVD',   'slug' => 'dvd',   'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => self::$audFieldId, 'name' => 'Adult', 'slug' => 'adult', 'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => self::$audFieldId, 'name' => 'Kids',  'slug' => 'kids',  'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        self::$booted = true;
    }

    /**
     * Truncate transactional tables and flush the cache between tests.
     *
     * @return void
     */
    private function resetData(): void
    {
        Capsule::table('forms')->delete();
        Capsule::table('form_field_config')->delete();
        Capsule::table('requests')->delete();
        Capsule::table('request_field_values')->delete();
        Capsule::table('selector_groups')->delete();
        Capsule::table('selector_group_field_option')->delete();
        Capsule::table('settings')->delete();

        // Keep seeded fields/options but remove any extras added by tests
        Capsule::table('fields')->where('key', 'not like', 'material_type')
            ->where('key', 'not like', 'audience')
            ->delete();

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
     * Seed a form and return its ID.
     *
     * @param  string  $slug
     * @param  string  $name
     * @return int
     */
    private function seedForm(string $slug, string $name = ''): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('forms')->insert([
            'name' => $name ?: ucfirst($slug), 'slug' => $slug,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    /**
     * Create a field and return it.
     *
     * @param  string      $key
     * @param  int         $sortOrder
     * @param  array|null  $condition
     * @param  bool        $required
     * @param  bool        $active
     * @param  string      $type
     * @return Field
     */
    private function createField(
        string $key,
        int $sortOrder = 0,
        ?array $condition = null,
        bool $required = false,
        bool $active = true,
        string $type = 'text',
    ): Field {
        $now = date('Y-m-d H:i:s');
        Capsule::table('fields')->insert([
            'key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key)),
            'type' => $type, 'step' => 2, 'scope' => 'both',
            'sort_order' => $sortOrder, 'active' => $active ? 1 : 0,
            'required' => $required ? 1 : 0,
            'condition' => $condition ? json_encode($condition) : null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return Field::where('key', $key)->firstOrFail();
    }

    /**
     * Attach a field to a form via form_field_config.
     *
     * @param  int         $formId
     * @param  int         $fieldId
     * @param  int         $sortOrder
     * @param  bool        $visible
     * @param  bool        $required
     * @param  string|null $labelOverride
     * @param  array|null  $conditionalLogic
     * @return void
     */
    private function attachToForm(
        int $formId,
        int $fieldId,
        int $sortOrder = 0,
        bool $visible = true,
        bool $required = false,
        ?string $labelOverride = null,
        ?array $conditionalLogic = null,
    ): void {
        $now = date('Y-m-d H:i:s');
        Capsule::table('form_field_config')->insert([
            'form_id'           => $formId,
            'field_id'          => $fieldId,
            'sort_order'        => $sortOrder,
            'visible'           => $visible ? 1 : 0,
            'required'          => $required ? 1 : 0,
            'label_override'    => $labelOverride,
            'conditional_logic' => $conditionalLogic ? json_encode($conditionalLogic) : null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    /**
     * Load visible fields for a form using the same logic as RequestForm/IllForm.
     *
     * @param  string  $formSlug
     * @return \Illuminate\Support\Collection<int, Field>
     */
    private function loadFormFields(string $formSlug): \Illuminate\Support\Collection
    {
        $form = Form::bySlug($formSlug);
        if (! $form) {
            return collect();
        }

        $configs = $form->fieldConfigs()
            ->with('field')
            ->where('visible', true)
            ->orderBy('sort_order')
            ->get();

        $fields = collect();
        foreach ($configs as $cfg) {
            $f = $cfg->field;
            if (! $f || ! $f->active) {
                continue;
            }
            $f->sort_order = $cfg->sort_order;
            $f->required   = (bool) $cfg->required;
            if ($cfg->label_override !== null && $cfg->label_override !== '') {
                $f->label = $cfg->label_override;
            }
            if ($cfg->conditional_logic) {
                $f->condition = $cfg->conditional_logic;
            }
            $fields->push($f);
        }

        return $fields->values();
    }

    // =========================================================================
    // 1. Sort order — form_field_config.sort_order determines display order
    // =========================================================================

    #[Test]
    public function fields_are_ordered_by_form_config_sort_order_not_base(): void
    {
        $formId = $this->seedForm('sfp');
        $title  = $this->createField('title', sortOrder: 40);
        $genre  = $this->createField('genre', sortOrder: 30);

        // Form config reverses the base order: genre=5, material_type=2, audience=1, title=3
        $this->attachToForm($formId, self::$audFieldId, sortOrder: 1);
        $this->attachToForm($formId, self::$mtFieldId,  sortOrder: 2);
        $this->attachToForm($formId, $title->id,        sortOrder: 3);
        $this->attachToForm($formId, $genre->id,        sortOrder: 5);

        $fields = $this->loadFormFields('sfp');
        $keys   = $fields->pluck('key')->all();

        $this->assertSame(['audience', 'material_type', 'title', 'genre'], $keys);
    }

    #[Test]
    public function base_sort_order_is_overridden_on_loaded_field(): void
    {
        $formId = $this->seedForm('sfp');
        // material_type has base sort_order=10, but form config sets it to 99
        $this->attachToForm($formId, self::$mtFieldId, sortOrder: 99);

        $fields = $this->loadFormFields('sfp');

        $this->assertSame(99, $fields->first()->sort_order);
    }

    // =========================================================================
    // 2. Visibility — form_field_config.visible gates field inclusion
    // =========================================================================

    #[Test]
    public function hidden_field_is_excluded_from_form(): void
    {
        $formId = $this->seedForm('sfp');
        $isbn   = $this->createField('isbn', sortOrder: 60);

        $this->attachToForm($formId, self::$mtFieldId, sortOrder: 1);
        $this->attachToForm($formId, $isbn->id,        sortOrder: 2, visible: false);

        $fields = $this->loadFormFields('sfp');
        $keys   = $fields->pluck('key')->all();

        $this->assertContains('material_type', $keys);
        $this->assertNotContains('isbn', $keys);
    }

    #[Test]
    public function inactive_field_is_excluded_even_when_config_visible(): void
    {
        $formId  = $this->seedForm('sfp');
        $console = $this->createField('console', sortOrder: 90, active: false);

        $this->attachToForm($formId, $console->id, sortOrder: 1, visible: true);

        $fields = $this->loadFormFields('sfp');

        $this->assertTrue($fields->isEmpty());
    }

    // =========================================================================
    // 3. Required — form_field_config.required overrides base field.required
    // =========================================================================

    #[Test]
    public function form_config_required_overrides_base_required(): void
    {
        $formId = $this->seedForm('sfp');
        // genre is NOT required at the base level
        $genre = $this->createField('genre', required: false);

        // But the SFP form marks it required
        $this->attachToForm($formId, $genre->id, sortOrder: 1, required: true);

        $fields = $this->loadFormFields('sfp');
        $loaded = $fields->firstWhere('key', 'genre');

        $this->assertTrue($loaded->required);
        $this->assertTrue($loaded->isRequiredFor(['material_type' => 'book']));
    }

    #[Test]
    public function form_config_not_required_overrides_base_required(): void
    {
        $formId = $this->seedForm('ill');
        // audience IS required at the base level (sort_order=20, required=1 from seed)
        // But the ILL form marks it not required
        $this->attachToForm($formId, self::$audFieldId, sortOrder: 1, required: false);

        $fields = $this->loadFormFields('ill');
        $loaded = $fields->firstWhere('key', 'audience');

        $this->assertFalse($loaded->required);
        $this->assertFalse($loaded->isRequiredFor(['material_type' => 'book']));
    }

    // =========================================================================
    // 4. Label override — form_field_config.label_override replaces field.label
    // =========================================================================

    #[Test]
    public function label_override_replaces_base_label(): void
    {
        $formId = $this->seedForm('ill');

        // material_type base label is "Material Type"
        $this->attachToForm($formId, self::$mtFieldId, sortOrder: 1, labelOverride: 'I want to borrow');

        $fields = $this->loadFormFields('ill');
        $loaded = $fields->firstWhere('key', 'material_type');

        $this->assertSame('I want to borrow', $loaded->label);
    }

    #[Test]
    public function null_label_override_keeps_base_label(): void
    {
        $formId = $this->seedForm('sfp');

        $this->attachToForm($formId, self::$mtFieldId, sortOrder: 1, labelOverride: null);

        $fields = $this->loadFormFields('sfp');
        $loaded = $fields->firstWhere('key', 'material_type');

        $this->assertSame('Material Type', $loaded->label);
    }

    #[Test]
    public function empty_string_label_override_keeps_base_label(): void
    {
        $formId = $this->seedForm('sfp');

        $this->attachToForm($formId, self::$mtFieldId, sortOrder: 1, labelOverride: '');

        $fields = $this->loadFormFields('sfp');
        $loaded = $fields->firstWhere('key', 'material_type');

        $this->assertSame('Material Type', $loaded->label);
    }

    // =========================================================================
    // 5. Conditional logic — per-form override and base fallback
    // =========================================================================

    #[Test]
    public function per_form_condition_overrides_base_condition(): void
    {
        $formId = $this->seedForm('sfp');

        // Base condition: visible only for books
        $genre = $this->createField('genre', condition: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['book']]],
        ]);

        // SFP form overrides: visible only for DVDs
        $this->attachToForm($formId, $genre->id, sortOrder: 1, conditionalLogic: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['dvd']]],
        ]);

        $fields = $this->loadFormFields('sfp');
        $loaded = $fields->firstWhere('key', 'genre');

        // Should now be visible for dvd (form override), NOT for book (base)
        $this->assertTrue($loaded->isVisibleFor(['material_type' => 'dvd']));
        $this->assertFalse($loaded->isVisibleFor(['material_type' => 'book']));
    }

    #[Test]
    public function base_condition_is_used_when_no_per_form_override(): void
    {
        $formId = $this->seedForm('sfp');

        // Base condition: visible only for books
        $console = $this->createField('console', condition: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['book']]],
        ]);

        // No conditional_logic on the form config
        $this->attachToForm($formId, $console->id, sortOrder: 1);

        $fields = $this->loadFormFields('sfp');
        $loaded = $fields->firstWhere('key', 'console');

        // Base condition still applies
        $this->assertTrue($loaded->isVisibleFor(['material_type' => 'book']));
        $this->assertFalse($loaded->isVisibleFor(['material_type' => 'dvd']));
    }

    #[Test]
    public function per_form_condition_with_match_any(): void
    {
        $formId = $this->seedForm('sfp');
        $notes  = $this->createField('notes');

        $this->attachToForm($formId, $notes->id, sortOrder: 1, conditionalLogic: [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
                ['field' => 'audience', 'operator' => 'in', 'values' => ['kids']],
            ],
        ]);

        $fields = $this->loadFormFields('sfp');
        $loaded = $fields->firstWhere('key', 'notes');

        $this->assertTrue($loaded->isVisibleFor(['material_type' => 'book', 'audience' => 'adult']));
        $this->assertTrue($loaded->isVisibleFor(['material_type' => 'dvd', 'audience' => 'kids']));
        $this->assertFalse($loaded->isVisibleFor(['material_type' => 'dvd', 'audience' => 'adult']));
    }

    #[Test]
    public function per_form_condition_with_not_in(): void
    {
        $formId  = $this->seedForm('ill');
        $edition = $this->createField('edition');

        $this->attachToForm($formId, $edition->id, sortOrder: 1, conditionalLogic: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'not_in', 'values' => ['dvd', 'blu-ray']],
            ],
        ]);

        $fields = $this->loadFormFields('ill');
        $loaded = $fields->firstWhere('key', 'edition');

        $this->assertTrue($loaded->isVisibleFor(['material_type' => 'book']));
        $this->assertFalse($loaded->isVisibleFor(['material_type' => 'dvd']));
    }

    #[Test]
    public function required_field_hidden_by_condition_is_not_required(): void
    {
        $formId = $this->seedForm('sfp');
        $genre  = $this->createField('genre');

        $this->attachToForm($formId, $genre->id, sortOrder: 1, required: true, conditionalLogic: [
            'match' => 'all',
            'rules' => [['field' => 'material_type', 'operator' => 'in', 'values' => ['book']]],
        ]);

        $fields = $this->loadFormFields('sfp');
        $loaded = $fields->firstWhere('key', 'genre');

        // Required + visible → required
        $this->assertTrue($loaded->isRequiredFor(['material_type' => 'book']));
        // Required + hidden by condition → not required
        $this->assertFalse($loaded->isRequiredFor(['material_type' => 'dvd']));
    }

    // =========================================================================
    // 6. Different forms get independent configs
    // =========================================================================

    #[Test]
    public function same_field_has_different_config_per_form(): void
    {
        $sfpId = $this->seedForm('sfp');
        $illId = $this->seedForm('ill');
        $genre = $this->createField('genre');

        // SFP: genre is required, sort=1
        $this->attachToForm($sfpId, $genre->id, sortOrder: 1, required: true, labelOverride: 'Genre (SFP)');
        // ILL: genre is optional, sort=5, different label
        $this->attachToForm($illId, $genre->id, sortOrder: 5, required: false, labelOverride: 'Category');

        $sfpFields = $this->loadFormFields('sfp');
        $illFields = $this->loadFormFields('ill');

        $sfpGenre = $sfpFields->firstWhere('key', 'genre');
        $illGenre = $illFields->firstWhere('key', 'genre');

        $this->assertSame('Genre (SFP)', $sfpGenre->label);
        $this->assertTrue($sfpGenre->required);
        $this->assertSame(1, $sfpGenre->sort_order);

        $this->assertSame('Category', $illGenre->label);
        $this->assertFalse($illGenre->required);
        $this->assertSame(5, $illGenre->sort_order);
    }

    // =========================================================================
    // 7. PatronRequest::fieldValueLabel() — EAV slug→name resolution
    // =========================================================================

    #[Test]
    public function field_value_label_resolves_slug_to_option_name(): void
    {
        $request = PatronRequest::create(['request_kind' => 'sfp']);

        RequestFieldValue::create([
            'request_id' => $request->id,
            'field_id'   => self::$mtFieldId,
            'value'      => 'book',
        ]);

        // Force reload relations
        $request->load('fieldValues.field');

        $this->assertSame('Book', $request->fieldValueLabel('material_type'));
    }

    #[Test]
    public function field_value_label_returns_slug_when_option_not_found(): void
    {
        $request = PatronRequest::create(['request_kind' => 'sfp']);

        RequestFieldValue::create([
            'request_id' => $request->id,
            'field_id'   => self::$mtFieldId,
            'value'      => 'nonexistent-slug',
        ]);

        $request->load('fieldValues.field');

        $this->assertSame('nonexistent-slug', $request->fieldValueLabel('material_type'));
    }

    #[Test]
    public function field_value_label_returns_null_when_no_value_stored(): void
    {
        $request = PatronRequest::create(['request_kind' => 'sfp']);
        $request->load('fieldValues.field');

        $this->assertNull($request->fieldValueLabel('material_type'));
    }

    #[Test]
    public function field_value_returns_raw_slug(): void
    {
        $request = PatronRequest::create(['request_kind' => 'sfp']);

        RequestFieldValue::create([
            'request_id' => $request->id,
            'field_id'   => self::$audFieldId,
            'value'      => 'adult',
        ]);

        $request->load('fieldValues.field');

        $this->assertSame('adult', $request->fieldValue('audience'));
    }

    // =========================================================================
    // 8. SelectorGroup::fieldOptions() — unified pivot
    // =========================================================================

    #[Test]
    public function selector_group_field_options_returns_attached_options(): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('selector_groups')->insert([
            'id' => 1, 'name' => 'Adult Books', 'active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $bookId  = (int) Capsule::table('field_options')->where('slug', 'book')->value('id');
        $adultId = (int) Capsule::table('field_options')->where('slug', 'adult')->value('id');

        Capsule::table('selector_group_field_option')->insert([
            ['selector_group_id' => 1, 'field_option_id' => $bookId],
            ['selector_group_id' => 1, 'field_option_id' => $adultId],
        ]);

        $group   = SelectorGroup::find(1);
        $options = $group->fieldOptions;

        $this->assertCount(2, $options);
        $slugs = $options->pluck('slug')->sort()->values()->all();
        $this->assertSame(['adult', 'book'], $slugs);
    }

    #[Test]
    public function selector_group_field_options_can_filter_by_field(): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('selector_groups')->insert([
            'id' => 2, 'name' => 'Kids DVDs', 'active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $dvdId  = (int) Capsule::table('field_options')->where('slug', 'dvd')->value('id');
        $kidsId = (int) Capsule::table('field_options')->where('slug', 'kids')->value('id');

        Capsule::table('selector_group_field_option')->insert([
            ['selector_group_id' => 2, 'field_option_id' => $dvdId],
            ['selector_group_id' => 2, 'field_option_id' => $kidsId],
        ]);

        $group = SelectorGroup::find(2);

        $materialTypes = $group->fieldOptions()
            ->whereHas('field', fn ($q) => $q->where('key', 'material_type'))
            ->get();

        $audiences = $group->fieldOptions()
            ->whereHas('field', fn ($q) => $q->where('key', 'audience'))
            ->get();

        $this->assertCount(1, $materialTypes);
        $this->assertSame('dvd', $materialTypes->first()->slug);

        $this->assertCount(1, $audiences);
        $this->assertSame('kids', $audiences->first()->slug);
    }
}
