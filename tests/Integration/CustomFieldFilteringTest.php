<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use Dcplibrary\Sfp\Models\SfpRequest;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CustomFieldFilteringTest extends TestCase
{
    private static bool $booted = false;

    private function bootDatabase(): void
    {
        if (self::$booted) return;

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->schema();

        $schema->create('material_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->boolean('has_other_text')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('request_kind')->default('sfp');
            $table->integer('assigned_to_user_id')->nullable();
            $table->integer('material_type_id')->nullable();
            $table->integer('audience_id')->nullable();
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

        self::$booted = true;
    }

    #[Test]
    public function filtering_ill_requests_by_material_type_id_returns_only_matching_requests(): void
    {
        $this->bootDatabase();
        Cache::flush();

        $now = date('Y-m-d H:i:s');
        Capsule::table('material_types')->insert([
            ['id' => 1, 'name' => 'Book', 'slug' => 'book', 'active' => 1, 'has_other_text' => 0, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'DVD', 'slug' => 'dvd', 'active' => 1, 'has_other_text' => 0, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $rBook = SfpRequest::create(['request_kind' => 'ill', 'material_type_id' => 1, 'audience_id' => null]);
        $rDvd  = SfpRequest::create(['request_kind' => 'ill', 'material_type_id' => 2, 'audience_id' => null]);
        $rSfp  = SfpRequest::create(['request_kind' => 'sfp', 'material_type_id' => 1, 'audience_id' => 1]);

        $visible = SfpRequest::query()
            ->where('request_kind', 'ill')
            ->where('material_type_id', 1)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$rBook->id], $visible);
        $this->assertNotContains($rDvd->id, $visible);
        $this->assertNotContains($rSfp->id, $visible);
    }
}

