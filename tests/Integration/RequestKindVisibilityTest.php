<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RequestKindVisibilityTest extends TestCase
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

        $schema->create('sfp_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->default('selector');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Minimal tables required by the selector-group EXISTS query in scopeVisibleTo.
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

    private function insertRequest(string $kind, ?int $mt = null, ?int $aud = null): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'request_kind' => $kind,
            'material_type_id' => $mt,
            'audience_id' => $aud,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    private function resetData(): void
    {
        Capsule::table('requests')->delete();
        Capsule::table('sfp_users')->delete();
        Capsule::table('selector_group_user')->delete();
        Capsule::table('selector_group_material_type')->delete();
        Capsule::table('selector_group_audience')->delete();
        Capsule::table('settings')->delete();
        Cache::flush();
    }

    #[Test]
    public function ill_requests_are_visible_only_to_members_of_ill_group_in_scoped_mode(): void
    {
        $this->bootDatabase();
        $this->resetData();

        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_users')->insert([
            ['id' => 10, 'name' => 'Staff', 'email' => 'staff@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'IllMember', 'email' => 'ill@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Settings: scoped mode, strict groups on, ill group id = 999
        Capsule::table('settings')->insert([
            ['key' => 'requests_visibility_open_access', 'value' => '0', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'requests_visibility_strict_groups', 'value' => '1', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'assignment_enabled', 'value' => '0', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'ill_selector_group_id', 'value' => '999', 'type' => 'integer', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Grant ill group membership only to user 11
        Capsule::table('selector_group_user')->insert([
            ['selector_group_id' => 999, 'user_id' => 11],
        ]);

        $ill1 = $this->insertRequest('ill');
        $sfp1 = $this->insertRequest('sfp', mt: 1, aud: 1);
        $ill2 = $this->insertRequest('ill');

        /** @var User $user */
        $user = User::findOrFail(10);
        $userIll = User::findOrFail(11);

        $visible = SfpRequest::query()->visibleTo($user)->orderBy('id')->pluck('id')->all();
        $visibleIll = SfpRequest::query()->visibleTo($userIll)->orderBy('id')->pluck('id')->all();

        $this->assertSame([], $visible, 'Non-member should not see ILL');
        $this->assertSame([$ill1, $ill2], $visibleIll, 'ILL group member should see ILL');
        $this->assertNotContains($sfp1, $visibleIll);
    }

    #[Test]
    public function admin_sees_all_kinds(): void
    {
        $this->bootDatabase();
        $this->resetData();

        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_users')->insert([
            ['id' => 1, 'name' => 'Admin', 'email' => 'a@example.com', 'role' => 'admin', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Capsule::table('settings')->insert([
            ['key' => 'requests_visibility_open_access', 'value' => '0', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'assignment_enabled', 'value' => '0', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'ill_selector_group_id', 'value' => '999', 'type' => 'integer', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $sfp1 = $this->insertRequest('sfp', mt: 1, aud: 1);
        $ill1 = $this->insertRequest('ill');

        /** @var User $user */
        $user = User::findOrFail(1);

        $visible = SfpRequest::query()->visibleTo($user)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$sfp1, $ill1], $visible);
    }
}

