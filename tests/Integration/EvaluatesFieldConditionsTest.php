<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Livewire\Concerns\EvaluatesFieldConditions;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the EvaluatesFieldConditions trait.
 *
 * Verifies that formConditionState() correctly builds a slug-based state
 * map from component properties (ID lookups, dedicated string properties,
 * and the $custom array), and that buildVisibilityMap() evaluates field
 * conditions against that state.
 *
 * Uses an in-memory SQLite database for FieldOption::find() lookups.
 *
 * @see \Dcplibrary\Requests\Livewire\Concerns\EvaluatesFieldConditions
 */
class EvaluatesFieldConditionsTest extends TestCase
{
    /** @var bool */
    private static bool $booted = false;

    /** @var int FieldOption ID for 'book'. */
    private static int $bookOptId;

    /** @var int FieldOption ID for 'dvd'. */
    private static int $dvdOptId;

    /** @var int FieldOption ID for 'video-game'. */
    private static int $videoGameOptId;

    /** @var int FieldOption ID for 'adult'. */
    private static int $adultOptId;

    /** @var int FieldOption ID for 'kids'. */
    private static int $kidsOptId;

    /**
     * Boot the in-memory SQLite database and seed lookup data.
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

        $schema->create('fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('select');
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

        $now = date('Y-m-d H:i:s');

        Capsule::table('fields')->insert([
            ['key' => 'material_type', 'label' => 'Material Type', 'type' => 'select', 'sort_order' => 10, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'audience',      'label' => 'Audience',      'type' => 'select', 'sort_order' => 20, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $mtFieldId  = (int) Capsule::table('fields')->where('key', 'material_type')->value('id');
        $audFieldId = (int) Capsule::table('fields')->where('key', 'audience')->value('id');

        Capsule::table('field_options')->insert([
            ['field_id' => $mtFieldId, 'name' => 'Book',       'slug' => 'book',       'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => $mtFieldId, 'name' => 'DVD',        'slug' => 'dvd',        'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => $mtFieldId, 'name' => 'Video Game', 'slug' => 'video-game', 'sort_order' => 3, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => $audFieldId, 'name' => 'Adult', 'slug' => 'adult', 'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => $audFieldId, 'name' => 'Kids',  'slug' => 'kids',  'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        self::$bookOptId      = (int) Capsule::table('field_options')->where('slug', 'book')->value('id');
        self::$dvdOptId       = (int) Capsule::table('field_options')->where('slug', 'dvd')->value('id');
        self::$videoGameOptId = (int) Capsule::table('field_options')->where('slug', 'video-game')->value('id');
        self::$adultOptId     = (int) Capsule::table('field_options')->where('slug', 'adult')->value('id');
        self::$kidsOptId      = (int) Capsule::table('field_options')->where('slug', 'kids')->value('id');

        self::$booted = true;
    }

    /** {@inheritdoc} */
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a full stub (SFP-like) with all optional properties.
     *
     * @param  array  $overrides  Property overrides.
     * @return ConditionEvalFullStub
     */
    private function fullStub(array $overrides = []): ConditionEvalFullStub
    {
        $stub = new ConditionEvalFullStub();
        foreach ($overrides as $key => $value) {
            $stub->{$key} = $value;
        }

        return $stub;
    }

    /**
     * Build a minimal stub (ILL-like) — no audience_id, genre, or console.
     *
     * @param  array  $overrides  Property overrides.
     * @return ConditionEvalMinimalStub
     */
    private function minimalStub(array $overrides = []): ConditionEvalMinimalStub
    {
        $stub = new ConditionEvalMinimalStub();
        foreach ($overrides as $key => $value) {
            $stub->{$key} = $value;
        }

        return $stub;
    }

    // =========================================================================
    // 1. formConditionState — material_type_id resolves to slug
    // =========================================================================

    #[Test]
    public function state_resolves_material_type_id_to_slug(): void
    {
        $stub  = $this->fullStub(['material_type_id' => self::$bookOptId]);
        $state = $stub->getConditionState();

        $this->assertSame('book', $state['material_type']);
    }

    #[Test]
    public function state_produces_empty_slug_when_material_type_is_null(): void
    {
        $stub  = $this->fullStub();
        $state = $stub->getConditionState();

        $this->assertSame('', $state['material_type']);
    }

    // =========================================================================
    // 2. formConditionState — audience_id (optional property)
    // =========================================================================

    #[Test]
    public function state_includes_audience_slug_when_property_exists(): void
    {
        $stub = $this->fullStub([
            'material_type_id' => self::$bookOptId,
            'audience_id'      => self::$adultOptId,
        ]);
        $state = $stub->getConditionState();

        $this->assertSame('adult', $state['audience']);
    }

    #[Test]
    public function state_excludes_audience_when_property_missing(): void
    {
        $stub  = $this->minimalStub(['material_type_id' => self::$bookOptId]);
        $state = $stub->getConditionState();

        $this->assertArrayNotHasKey('audience', $state);
    }

    // =========================================================================
    // 3. formConditionState — dedicated string properties (genre, console)
    // =========================================================================

    #[Test]
    public function state_includes_dedicated_string_properties(): void
    {
        $stub = $this->fullStub([
            'material_type_id' => self::$videoGameOptId,
            'genre'            => 'action',
            'console'          => 'switch',
        ]);
        $state = $stub->getConditionState();

        $this->assertSame('action', $state['genre']);
        $this->assertSame('switch', $state['console']);
    }

    #[Test]
    public function state_excludes_empty_string_properties(): void
    {
        $stub  = $this->fullStub(['material_type_id' => self::$bookOptId]);
        $state = $stub->getConditionState();

        // genre and console default to '' — should not appear in state.
        $this->assertArrayNotHasKey('genre', $state);
        $this->assertArrayNotHasKey('console', $state);
    }

    // =========================================================================
    // 4. formConditionState — custom array values
    // =========================================================================

    #[Test]
    public function state_includes_custom_values(): void
    {
        $stub = $this->fullStub([
            'material_type_id' => self::$bookOptId,
            'custom'           => ['pickup_location' => 'main-branch', 'format' => 'paperback'],
        ]);
        $state = $stub->getConditionState();

        $this->assertSame('main-branch', $state['pickup_location']);
        $this->assertSame('paperback', $state['format']);
    }

    #[Test]
    public function state_ignores_empty_custom_values(): void
    {
        $stub = $this->fullStub([
            'material_type_id' => self::$bookOptId,
            'custom'           => ['pickup_location' => '', 'format' => 'paperback'],
        ]);
        $state = $stub->getConditionState();

        $this->assertArrayNotHasKey('pickup_location', $state);
        $this->assertSame('paperback', $state['format']);
    }

    // =========================================================================
    // 5. formConditionState — precedence (dedicated property > custom)
    // =========================================================================

    #[Test]
    public function dedicated_property_takes_precedence_over_custom(): void
    {
        $stub = $this->fullStub([
            'material_type_id' => self::$bookOptId,
            'genre'            => 'fiction',
            'custom'           => ['genre' => 'mystery'],
        ]);
        $state = $stub->getConditionState();

        $this->assertSame('fiction', $state['genre']);
    }

    // =========================================================================
    // 6. buildVisibilityMap — evaluates conditions against state
    // =========================================================================

    #[Test]
    public function visibility_map_evaluates_field_conditions(): void
    {
        $stub = $this->fullStub([
            'material_type_id' => self::$videoGameOptId,
        ]);

        // Console: visible when material_type is 'video-game'.
        $console = new Field();
        $console->key       = 'console';
        $console->active    = true;
        $console->condition = [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['video-game']],
            ],
        ];

        // Genre: visible when material_type is 'book'.
        $genre = new Field();
        $genre->key       = 'genre';
        $genre->active    = true;
        $genre->condition = [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ];

        // Title: always visible (no condition).
        $title = new Field();
        $title->key       = 'title';
        $title->active    = true;
        $title->condition = null;

        $map = $stub->getVisibilityMap([$console, $genre, $title]);

        $this->assertTrue($map['console']);
        $this->assertFalse($map['genre']);
        $this->assertTrue($map['title']);
    }

    #[Test]
    public function visibility_map_handles_inactive_fields(): void
    {
        $stub = $this->fullStub(['material_type_id' => self::$bookOptId]);

        $inactive = new Field();
        $inactive->key       = 'hidden';
        $inactive->active    = false;
        $inactive->condition = null;

        $map = $stub->getVisibilityMap([$inactive]);

        $this->assertFalse($map['hidden']);
    }

    // =========================================================================
    // 7. isFieldVisible — helper behaviour
    // =========================================================================

    #[Test]
    public function is_field_visible_returns_false_for_missing_key(): void
    {
        $stub = $this->fullStub();
        $map  = ['title' => true, 'genre' => false];

        $this->assertTrue($stub->checkFieldVisible('title', $map));
        $this->assertFalse($stub->checkFieldVisible('genre', $map));
        $this->assertFalse($stub->checkFieldVisible('nonexistent', $map));
    }

    // =========================================================================
    // 8. End-to-end: console visible for video-game via ILL-like stub
    // =========================================================================

    #[Test]
    public function minimal_stub_builds_state_from_custom_only(): void
    {
        $stub = $this->minimalStub([
            'material_type_id' => self::$videoGameOptId,
            'custom'           => ['console' => 'switch'],
        ]);
        $state = $stub->getConditionState();

        $this->assertSame('video-game', $state['material_type']);
        $this->assertSame('switch', $state['console']);
        $this->assertArrayNotHasKey('audience', $state);
        $this->assertArrayNotHasKey('genre', $state);
    }
}

// ─── Test Stubs ──────────────────────────────────────────────────────────────
// Concrete classes that expose the protected trait methods for testing.
// Not autoloaded — only used within this test file.

/**
 * Full stub (SFP-like): has audience_id, genre, and console properties.
 *
 * @internal
 */
class ConditionEvalFullStub
{
    use EvaluatesFieldConditions;

    /** @var int|null */
    public ?int $material_type_id = null;

    /** @var int|null */
    public ?int $audience_id = null;

    /** @var string */
    public string $genre = '';

    /** @var string */
    public string $console = '';

    /** @var array<string, mixed> */
    public array $custom = [];

    /**
     * @return array<string, string|null>
     */
    public function getConditionState(): array
    {
        return $this->formConditionState();
    }

    /**
     * @param  iterable  $fields
     * @return array<string, bool>
     */
    public function getVisibilityMap(iterable $fields): array
    {
        return $this->buildVisibilityMap($fields);
    }

    /**
     * @param  string               $key
     * @param  array<string, bool>  $map
     * @return bool
     */
    public function checkFieldVisible(string $key, array $map): bool
    {
        return $this->isFieldVisible($key, $map);
    }
}

/**
 * Minimal stub (ILL-like): no audience_id, genre, or console properties.
 *
 * @internal
 */
class ConditionEvalMinimalStub
{
    use EvaluatesFieldConditions;

    /** @var int|null */
    public ?int $material_type_id = null;

    /** @var array<string, mixed> */
    public array $custom = [];

    /**
     * @return array<string, string|null>
     */
    public function getConditionState(): array
    {
        return $this->formConditionState();
    }

    /**
     * @param  iterable  $fields
     * @return array<string, bool>
     */
    public function getVisibilityMap(iterable $fields): array
    {
        return $this->buildVisibilityMap($fields);
    }

    /**
     * @param  string               $key
     * @param  array<string, bool>  $map
     * @return bool
     */
    public function checkFieldVisible(string $key, array $map): bool
    {
        return $this->isFieldVisible($key, $map);
    }
}
