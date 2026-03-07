<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssignmentAndVisibilityTest extends TestCase
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
            $table->integer('material_type_id')->nullable();
            $table->integer('audience_id')->nullable();
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

        self::$booted = true;
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

    private function seedSettings(array $pairs): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($pairs as $key => $payload) {
            Capsule::table('settings')->insert([
                'key' => $key,
                'value' => (string) $payload['value'],
                'type' => $payload['type'] ?? 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Cache::flush();
    }

    private function insertRequest(string $kind, ?int $mt = null, ?int $aud = null, ?int $assignedTo = null): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'request_kind' => $kind,
            'material_type_id' => $mt,
            'audience_id' => $aud,
            'assigned_to_user_id' => $assignedTo,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    #[Test]
    public function open_access_mode_allows_any_staff_to_view_all_requests(): void
    {
        $this->bootDatabase();
        $this->resetData();

        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_users')->insert([
            ['id' => 1, 'name' => 'Staff', 'email' => 's@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->seedSettings([
            'requests_visibility_open_access' => ['value' => 1, 'type' => 'boolean'],
            'assignment_enabled' => ['value' => 0, 'type' => 'boolean'],
            'ill_selector_group_id' => ['value' => 999, 'type' => 'integer'],
        ]);

        $sfp = $this->insertRequest('sfp', mt: 1, aud: 1);
        $ill = $this->insertRequest('ill');

        $user = User::findOrFail(1);
        $visible = SfpRequest::query()->visibleTo($user)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$sfp, $ill], $visible);
    }

    #[Test]
    public function strict_groups_off_allows_staff_to_view_all_sfp_but_ill_is_still_gated(): void
    {
        $this->bootDatabase();
        $this->resetData();

        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_users')->insert([
            ['id' => 2, 'name' => 'Staff', 'email' => 's2@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->seedSettings([
            'requests_visibility_open_access' => ['value' => 0, 'type' => 'boolean'],
            'requests_visibility_strict_groups' => ['value' => 0, 'type' => 'boolean'],
            'assignment_enabled' => ['value' => 0, 'type' => 'boolean'],
            'ill_selector_group_id' => ['value' => 999, 'type' => 'integer'],
        ]);

        $sfp1 = $this->insertRequest('sfp', mt: 1, aud: 10);
        $sfp2 = $this->insertRequest('sfp', mt: 2, aud: 20);
        $ill1 = $this->insertRequest('ill');

        $user = User::findOrFail(2);
        $visible = SfpRequest::query()->visibleTo($user)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$sfp1, $sfp2], $visible);
        $this->assertNotContains($ill1, $visible);
    }

    #[Test]
    public function assignment_override_allows_assignee_to_view_even_when_scoped_filters_block(): void
    {
        $this->bootDatabase();
        $this->resetData();

        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_users')->insert([
            ['id' => 3, 'name' => 'Assignee', 'email' => 'a@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->seedSettings([
            'requests_visibility_open_access' => ['value' => 0, 'type' => 'boolean'],
            'requests_visibility_strict_groups' => ['value' => 1, 'type' => 'boolean'],
            'assignment_enabled' => ['value' => 1, 'type' => 'boolean'],
            'ill_selector_group_id' => ['value' => 999, 'type' => 'integer'],
        ]);

        // No selector group memberships, so strict scoping would normally block SFP.
        $blockedSfp = $this->insertRequest('sfp', mt: 1, aud: 10, assignedTo: 3);

        $user = User::findOrFail(3);
        $visible = SfpRequest::query()->visibleTo($user)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$blockedSfp], $visible);
    }

    #[Test]
    public function ill_group_gating_blocks_non_members_but_assignment_override_can_allow(): void
    {
        $this->bootDatabase();
        $this->resetData();

        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_users')->insert([
            ['id' => 4, 'name' => 'NonMember', 'email' => 'n@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Member', 'email' => 'm@example.com', 'role' => 'selector', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Capsule::table('selector_group_user')->insert([
            ['selector_group_id' => 999, 'user_id' => 5],
        ]);

        $this->seedSettings([
            'requests_visibility_open_access' => ['value' => 0, 'type' => 'boolean'],
            'requests_visibility_strict_groups' => ['value' => 1, 'type' => 'boolean'],
            'assignment_enabled' => ['value' => 1, 'type' => 'boolean'],
            'ill_selector_group_id' => ['value' => 999, 'type' => 'integer'],
        ]);

        $illAssignedToNonMember = $this->insertRequest('ill', assignedTo: 4);
        $illUnassigned = $this->insertRequest('ill');

        $nonMember = User::findOrFail(4);
        $member = User::findOrFail(5);

        $visibleNonMember = SfpRequest::query()->visibleTo($nonMember)->orderBy('id')->pluck('id')->all();
        $visibleMember = SfpRequest::query()->visibleTo($member)->orderBy('id')->pluck('id')->all();

        // Assignee override: sees assigned ILL, but not unassigned ILL.
        $this->assertSame([$illAssignedToNonMember], $visibleNonMember);
        $this->assertSame([$illAssignedToNonMember, $illUnassigned], $visibleMember);
    }
}

