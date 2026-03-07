<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for request visibility using real SQLite queries.
 *
 * These tests verify that selector group scoping does NOT "bridge" across
 * multiple groups (no cartesian product of material types × audiences).
 */
class RequestVisibilityBySelectorGroupTest extends TestCase
{
    private static bool $booted = false;

    private function bootDatabase(): void
    {
        if (self::$booted) {
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->schema();

        $schema->create('sfp_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->default('selector');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $schema->create('selector_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $schema->create('material_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $schema->create('audiences', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $schema->create('selector_group_user', function (Blueprint $table) {
            $table->integer('selector_group_id');
            $table->integer('user_id');
        });

        $schema->create('selector_group_material_type', function (Blueprint $table) {
            $table->integer('selector_group_id');
            $table->integer('material_type_id');
        });

        $schema->create('selector_group_audience', function (Blueprint $table) {
            $table->integer('selector_group_id');
            $table->integer('audience_id');
        });

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('material_type_id')->nullable();
            $table->integer('audience_id')->nullable();
            $table->timestamps();
        });

        self::$booted = true;
    }

    private function insertRequest(int $materialTypeId, int $audienceId): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'material_type_id' => $materialTypeId,
            'audience_id' => $audienceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    #[Test]
    public function selector_groups_do_not_bridge_across_each_other(): void
    {
        $this->bootDatabase();

        $now = date('Y-m-d H:i:s');

        Capsule::table('material_types')->insert([
            ['id' => 1, 'name' => 'Book', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'DVD',  'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        Capsule::table('audiences')->insert([
            ['id' => 10, 'name' => 'Adult', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'name' => 'Kids',  'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Capsule::table('selector_groups')->insert([
            ['id' => 100, 'name' => 'Adult Books', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 200, 'name' => 'Kids DVDs',   'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Group 100 covers (material_type_id=1) × (audience_id=10)
        Capsule::table('selector_group_material_type')->insert([
            ['selector_group_id' => 100, 'material_type_id' => 1],
        ]);
        Capsule::table('selector_group_audience')->insert([
            ['selector_group_id' => 100, 'audience_id' => 10],
        ]);

        // Group 200 covers (material_type_id=2) × (audience_id=20)
        Capsule::table('selector_group_material_type')->insert([
            ['selector_group_id' => 200, 'material_type_id' => 2],
        ]);
        Capsule::table('selector_group_audience')->insert([
            ['selector_group_id' => 200, 'audience_id' => 20],
        ]);

        // Selector belongs to BOTH groups.
        Capsule::table('sfp_users')->insert([
            ['id' => 5, 'name' => 'Sel', 'email' => 'sel@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        Capsule::table('selector_group_user')->insert([
            ['selector_group_id' => 100, 'user_id' => 5],
            ['selector_group_id' => 200, 'user_id' => 5],
        ]);

        // Requests:
        // - Two "in-scope" requests (one per group)
        // - Two "bridged" requests that would be incorrectly visible if the code
        //   used union(material_types) AND union(audiences)
        $inScopeA = $this->insertRequest(1, 10);
        $inScopeB = $this->insertRequest(2, 20);
        $bridged1 = $this->insertRequest(1, 20); // Book + Kids (not covered by any group)
        $bridged2 = $this->insertRequest(2, 10); // DVD + Adult (not covered by any group)

        /** @var User $user */
        $user = User::findOrFail(5);

        $visible = SfpRequest::query()
            ->visibleTo($user)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$inScopeA, $inScopeB], $visible);
        $this->assertNotContains($bridged1, $visible);
        $this->assertNotContains($bridged2, $visible);
    }

    #[Test]
    public function selector_with_no_groups_sees_no_requests(): void
    {
        $this->bootDatabase();

        $now = date('Y-m-d H:i:s');

        Capsule::table('sfp_users')->insert([
            ['id' => 99, 'name' => 'Ungrouped', 'email' => 'u@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $r1 = $this->insertRequest(1, 10);
        $r2 = $this->insertRequest(2, 20);

        /** @var User $user */
        $user = User::findOrFail(99);

        $visible = SfpRequest::query()->visibleTo($user)->pluck('id')->all();

        $this->assertSame([], $visible, 'Ungrouped selector should see no requests');
        $this->assertNotContains($r1, $visible);
        $this->assertNotContains($r2, $visible);
    }
}

