<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the unified field-option resolution logic.
 *
 * Verifies that FieldOption queries, combined with FormFieldOptionOverride
 * records, produce the correct visible/ordered/labeled option sets —
 * the same logic used by IllForm::getFieldOptionsWithOverrides().
 *
 * @see \Dcplibrary\Requests\Livewire\IllForm::getFieldOptionsWithOverrides()
 * @see \Dcplibrary\Requests\Livewire\IllForm::getOptionsForField()
 */
class FieldOptionResolutionTest extends TestCase
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

        $schema->create('forms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $schema->create('form_field_option_overrides', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('form_id');
            $table->integer('field_id');
            $table->string('option_slug');
            $table->string('label_override')->nullable();
            $table->boolean('visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['form_id', 'field_id', 'option_slug'], 'ffo_unique');
        });

        self::$booted = true;
    }

    /**
     * Truncate all tables between tests.
     *
     * @return void
     */
    private function resetData(): void
    {
        Capsule::table('fields')->delete();
        Capsule::table('field_options')->delete();
        Capsule::table('forms')->delete();
        Capsule::table('form_field_option_overrides')->delete();
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
     * Replicate IllForm::getFieldOptionsWithOverrides() locally for testing.
     *
     * @param  int       $fieldId
     * @param  int|null  $formId
     * @return \Illuminate\Support\Collection
     */
    private function resolve(int $fieldId, ?int $formId = null): \Illuminate\Support\Collection
    {
        $base = FieldOption::where('field_id', $fieldId)->active()->ordered()->get();

        if (! $formId) {
            return $base->values()->map(fn (FieldOption $opt, int $i) => (object) [
                'id'         => $opt->id,
                'slug'       => $opt->slug,
                'name'       => $opt->name,
                'visible'    => true,
                'sort_order' => $opt->sort_order ?? ($i + 1) * 10000,
            ]);
        }

        $overrides = FormFieldOptionOverride::where('form_id', $formId)
            ->where('field_id', $fieldId)
            ->get()
            ->keyBy('option_slug');

        return $base->map(function (FieldOption $opt, int $i) use ($overrides) {
            $ov = $overrides->get($opt->slug);

            return (object) [
                'id'         => $opt->id,
                'slug'       => $opt->slug,
                'name'       => ($ov && $ov->label_override !== null && $ov->label_override !== '')
                    ? $ov->label_override
                    : $opt->name,
                'visible'    => $ov ? (bool) $ov->visible : true,
                'sort_order' => $ov ? $ov->sort_order : ($i + 1) * 10000,
            ];
        })->filter(fn ($o) => $o->visible)->sortBy('sort_order')->values();
    }

    /**
     * Convenience: slug => name from resolve().
     *
     * @param  int       $fieldId
     * @param  int|null  $formId
     * @return array<string, string>
     */
    private function resolveFlat(int $fieldId, ?int $formId = null): array
    {
        return $this->resolve($fieldId, $formId)->pluck('name', 'slug')->all();
    }

    /**
     * Seed a field and its options.
     *
     * @param  string  $key
     * @param  array   $options  ['slug' => 'name', ...]
     * @return int     field ID
     */
    private function seedField(string $key, array $options): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('fields')->insert([
            'key' => $key, 'label' => ucfirst($key), 'type' => 'select',
            'sort_order' => 0, 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $fieldId = (int) Capsule::connection()->getPdo()->lastInsertId();

        $i = 1;
        foreach ($options as $slug => $name) {
            Capsule::table('field_options')->insert([
                'field_id' => $fieldId, 'name' => $name, 'slug' => $slug,
                'sort_order' => $i++, 'active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return $fieldId;
    }

    /**
     * Seed a form.
     *
     * @param  string  $slug
     * @return int
     */
    private function seedForm(string $slug): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('forms')->insert([
            'name' => ucfirst($slug), 'slug' => $slug,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    // ── Tests ───────────────────────────────────────────────────────────────

    #[Test]
    public function returns_all_active_options_without_form(): void
    {
        $fieldId = $this->seedField('material_type', [
            'book' => 'Book', 'dvd' => 'DVD', 'audiobook' => 'Audiobook',
        ]);

        $result = $this->resolveFlat($fieldId);

        $this->assertSame(['book' => 'Book', 'dvd' => 'DVD', 'audiobook' => 'Audiobook'], $result);
    }

    #[Test]
    public function returns_all_options_when_form_has_no_overrides(): void
    {
        $fieldId = $this->seedField('audience', ['adult' => 'Adult', 'kids' => 'Kids']);
        $formId  = $this->seedForm('ill');

        $result = $this->resolveFlat($fieldId, $formId);

        $this->assertSame(['adult' => 'Adult', 'kids' => 'Kids'], $result);
    }

    #[Test]
    public function hidden_override_filters_option(): void
    {
        $fieldId = $this->seedField('genre', [
            'fiction' => 'Fiction', 'nonfiction' => 'Non-Fiction', 'mystery' => 'Mystery',
        ]);
        $formId = $this->seedForm('ill');

        $now = date('Y-m-d H:i:s');
        Capsule::table('form_field_option_overrides')->insert([
            'form_id' => $formId, 'field_id' => $fieldId, 'option_slug' => 'mystery',
            'visible' => 0, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $result = $this->resolveFlat($fieldId, $formId);

        $this->assertArrayHasKey('fiction', $result);
        $this->assertArrayHasKey('nonfiction', $result);
        $this->assertArrayNotHasKey('mystery', $result);
    }

    #[Test]
    public function label_override_replaces_name(): void
    {
        $fieldId = $this->seedField('audience', ['adult' => 'Adult', 'kids' => 'Kids']);
        $formId  = $this->seedForm('sfp');

        $now = date('Y-m-d H:i:s');
        Capsule::table('form_field_option_overrides')->insert([
            'form_id' => $formId, 'field_id' => $fieldId, 'option_slug' => 'kids',
            'label_override' => 'Children', 'visible' => 1, 'sort_order' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $result = $this->resolveFlat($fieldId, $formId);

        $this->assertSame('Children', $result['kids']);
        $this->assertSame('Adult', $result['adult']);
    }

    #[Test]
    public function sort_order_override_reorders_options(): void
    {
        $fieldId = $this->seedField('material_type', [
            'book' => 'Book', 'dvd' => 'DVD', 'audiobook' => 'Audiobook',
        ]);
        $formId = $this->seedForm('ill');

        $now = date('Y-m-d H:i:s');
        // Put DVD first, audiobook second, book third.
        Capsule::table('form_field_option_overrides')->insert([
            ['form_id' => $formId, 'field_id' => $fieldId, 'option_slug' => 'dvd',
             'visible' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['form_id' => $formId, 'field_id' => $fieldId, 'option_slug' => 'audiobook',
             'visible' => 1, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['form_id' => $formId, 'field_id' => $fieldId, 'option_slug' => 'book',
             'visible' => 1, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $slugs = array_keys($this->resolveFlat($fieldId, $formId));

        $this->assertSame(['dvd', 'audiobook', 'book'], $slugs);
    }

    #[Test]
    public function inactive_options_are_excluded(): void
    {
        $fieldId = $this->seedField('material_type', ['book' => 'Book', 'dvd' => 'DVD']);

        // Deactivate 'dvd' at the global level.
        Capsule::table('field_options')
            ->where('field_id', $fieldId)
            ->where('slug', 'dvd')
            ->update(['active' => 0]);

        $result = $this->resolveFlat($fieldId);

        $this->assertArrayHasKey('book', $result);
        $this->assertArrayNotHasKey('dvd', $result);
    }

    #[Test]
    public function different_forms_get_independent_overrides(): void
    {
        $fieldId = $this->seedField('audience', ['adult' => 'Adult', 'kids' => 'Kids', 'ya' => 'Young Adult']);
        $sfpId   = $this->seedForm('sfp');
        $illId   = $this->seedForm('ill');

        $now = date('Y-m-d H:i:s');
        // SFP: hide 'ya'
        Capsule::table('form_field_option_overrides')->insert([
            'form_id' => $sfpId, 'field_id' => $fieldId, 'option_slug' => 'ya',
            'visible' => 0, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        // ILL: hide 'kids'
        Capsule::table('form_field_option_overrides')->insert([
            'form_id' => $illId, 'field_id' => $fieldId, 'option_slug' => 'kids',
            'visible' => 0, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $sfpResult = $this->resolveFlat($fieldId, $sfpId);
        $illResult = $this->resolveFlat($fieldId, $illId);

        $this->assertArrayNotHasKey('ya', $sfpResult);
        $this->assertArrayHasKey('kids', $sfpResult);

        $this->assertArrayHasKey('ya', $illResult);
        $this->assertArrayNotHasKey('kids', $illResult);
    }
}
