<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\RequestCustomFieldValue;
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

        $schema->create('sfp_custom_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->string('label');
            $table->string('type', 30);
            $table->unsignedSmallInteger('step')->default(2);
            $table->string('request_kind', 20)->default('sfp');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('required')->default(false);
            $table->boolean('include_as_token')->default(false);
            $table->boolean('filterable')->default(false);
            $table->text('condition')->nullable();
            $table->timestamps();
        });

        $schema->create('sfp_custom_field_options', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('custom_field_id');
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $schema->create('sfp_request_custom_field_values', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('request_id');
            $table->integer('custom_field_id');
            $table->string('value_slug')->nullable();
            $table->text('value_text')->nullable();
            $table->timestamps();
        });

        self::$booted = true;
    }

    #[Test]
    public function filtering_by_value_slug_returns_only_matching_requests(): void
    {
        $this->bootDatabase();
        Cache::flush();

        $now = date('Y-m-d H:i:s');

        /** @var CustomField $field */
        $field = CustomField::create([
            'key' => 'borrow_type',
            'label' => 'Borrow type',
            'type' => 'radio',
            'step' => 2,
            'request_kind' => 'ill',
            'sort_order' => 1,
            'active' => true,
            'required' => true,
            'include_as_token' => true,
            'filterable' => true,
            'condition' => null,
        ]);

        CustomFieldOption::insert([
            ['custom_field_id' => $field->id, 'name' => 'Book', 'slug' => 'book', 'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['custom_field_id' => $field->id, 'name' => 'DVD',  'slug' => 'dvd',  'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $rBook = SfpRequest::create(['request_kind' => 'ill', 'material_type_id' => null, 'audience_id' => null]);
        $rDvd  = SfpRequest::create(['request_kind' => 'ill', 'material_type_id' => null, 'audience_id' => null]);
        $rSfp  = SfpRequest::create(['request_kind' => 'sfp', 'material_type_id' => 1, 'audience_id' => 1]);

        RequestCustomFieldValue::insert([
            ['request_id' => $rBook->id, 'custom_field_id' => $field->id, 'value_slug' => 'book', 'value_text' => null, 'created_at' => $now, 'updated_at' => $now],
            ['request_id' => $rDvd->id,  'custom_field_id' => $field->id, 'value_slug' => 'dvd',  'value_text' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Same EXISTS strategy as RequestController filter.
        $visible = SfpRequest::query()
            ->where('request_kind', 'ill')
            ->whereExists(function ($sub) use ($field) {
                $sub->selectRaw('1')
                    ->from('sfp_request_custom_field_values as rcfv')
                    ->whereColumn('rcfv.request_id', 'requests.id')
                    ->where('rcfv.custom_field_id', $field->id)
                    ->where('rcfv.value_slug', 'book');
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$rBook->id], $visible);
        $this->assertNotContains($rDvd->id, $visible);
        $this->assertNotContains($rSfp->id, $visible);
    }
}

