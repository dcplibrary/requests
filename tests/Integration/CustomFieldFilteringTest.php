<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\PatronRequest;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for filtering requests by field values (EAV).
 *
 * Replaces the former material_type_id column filter with a
 * request_field_values join against the unified field/option tables.
 *
 * @see \Dcplibrary\Requests\Models\RequestFieldValue
 */
class CustomFieldFilteringTest extends TestCase
{
    /** @var bool */
    private static bool $booted = false;

    /** @var int Field ID for 'material_type'. */
    private static int $mtFieldId;

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
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
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
            $table->timestamp('deleted_at')->nullable();
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

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('request_kind')->default('sfp');
            $table->integer('assigned_to_user_id')->nullable();
            $table->timestamps();
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

        // Seed material_type field and options.
        $now = date('Y-m-d H:i:s');
        Capsule::table('fields')->insert([
            'key' => 'material_type', 'label' => 'Material Type', 'type' => 'select',
            'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        self::$mtFieldId = (int) Capsule::table('fields')->where('key', 'material_type')->value('id');

        Capsule::table('field_options')->insert([
            ['field_id' => self::$mtFieldId, 'name' => 'Book', 'slug' => 'book', 'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => self::$mtFieldId, 'name' => 'DVD',  'slug' => 'dvd',  'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        self::$booted = true;
    }

    /**
     * Filter ILL requests by material_type value via EAV join.
     *
     * Verifies that the request_field_values table correctly stores and
     * filters material_type values that were previously on the requests table.
     */
    #[Test]
    public function filtering_ill_requests_by_material_type_value_returns_only_matching_requests(): void
    {
        $this->bootDatabase();
        Cache::flush();

        $now = date('Y-m-d H:i:s');

        // Create requests.
        $rBook = PatronRequest::create(['request_kind' => 'ill']);
        $rDvd  = PatronRequest::create(['request_kind' => 'ill']);
        $rSfp  = PatronRequest::create(['request_kind' => 'sfp']);

        // Store material_type as EAV in request_field_values.
        Capsule::table('request_field_values')->insert([
            ['request_id' => $rBook->id, 'field_id' => self::$mtFieldId, 'value' => 'book', 'created_at' => $now, 'updated_at' => $now],
            ['request_id' => $rDvd->id,  'field_id' => self::$mtFieldId, 'value' => 'dvd',  'created_at' => $now, 'updated_at' => $now],
            ['request_id' => $rSfp->id,  'field_id' => self::$mtFieldId, 'value' => 'book', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Filter ILL requests by material_type = 'book' via request_field_values join.
        $visible = PatronRequest::query()
            ->where('request_kind', 'ill')
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('request_field_values')
                    ->whereColumn('request_field_values.request_id', 'requests.id')
                    ->where('request_field_values.field_id', self::$mtFieldId)
                    ->where('request_field_values.value', 'book');
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$rBook->id], $visible);
        $this->assertNotContains($rDvd->id, $visible);
        $this->assertNotContains($rSfp->id, $visible);
    }

    /**
     * Filter by material_type using PatronRequest::scopeWhereFieldValue (kind null).
     *
     * Verifies the scope produces the same result as the manual whereExists used above.
     */
    #[Test]
    public function where_field_value_scope_filters_requests_by_material_type(): void
    {
        $this->bootDatabase();
        Cache::flush();

        Capsule::table('request_field_values')->delete();
        Capsule::table('requests')->delete();

        $now = date('Y-m-d H:i:s');

        $rBook = PatronRequest::create(['request_kind' => 'ill']);
        $rDvd  = PatronRequest::create(['request_kind' => 'ill']);
        $rSfp  = PatronRequest::create(['request_kind' => 'sfp']);

        Capsule::table('request_field_values')->insert([
            ['request_id' => $rBook->id, 'field_id' => self::$mtFieldId, 'value' => 'book', 'created_at' => $now, 'updated_at' => $now],
            ['request_id' => $rDvd->id,  'field_id' => self::$mtFieldId, 'value' => 'dvd',  'created_at' => $now, 'updated_at' => $now],
            ['request_id' => $rSfp->id,  'field_id' => self::$mtFieldId, 'value' => 'book', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $visible = PatronRequest::query()
            ->where('request_kind', 'ill')
            ->whereFieldValue('material_type', 'book', null)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$rBook->id], $visible);
    }
}
