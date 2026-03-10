<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for SfpRequest::scopeVisibleTo() permission logic.
 *
 * Boots a single SQLite in-memory database once, then resets data between tests.
 * No external services or MySQL required — safe for GitHub Actions CI.
 *
 * @see \Dcplibrary\Sfp\Models\SfpRequest::scopeVisibleTo()
 * @see docs/permissions.md
 */
class RequestPermissionsTest extends TestCase
{
    /** @var bool Whether the SQLite schema has been created. */
    private static bool $booted = false;

    // -------------------------------------------------------------------------
    // Shared setup
    // -------------------------------------------------------------------------

    /**
     * Boot the in-memory SQLite database and create all required tables.
     *
     * Called once per process; subsequent calls are no-ops.
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

    /**
     * Truncate all tables and flush the cache between tests.
     *
     * @return void
     */
    private function resetData(): void
    {
        Capsule::table('requests')->delete();
        Capsule::table('sfp_users')->delete();
        Capsule::table('selector_groups')->delete();
        Capsule::table('selector_group_user')->delete();
        Capsule::table('selector_group_material_type')->delete();
        Capsule::table('selector_group_audience')->delete();
        Capsule::table('settings')->delete();
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
     * Seed one or more settings rows.
     *
     * @param array<string, array{value: mixed, type?: string}> $pairs
     * @return void
     */
    private function seedSettings(array $pairs): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($pairs as $key => $payload) {
            Capsule::table('settings')->insert([
                'key'        => $key,
                'value'      => (string) $payload['value'],
                'type'       => $payload['type'] ?? 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Cache::flush();
    }

    /**
     * Seed the four visibility-related settings in one call.
     *
     * @param bool $openAccess
     * @param bool $strictGroups
     * @param bool $assignmentEnabled
     * @param int  $illGroupId
     * @return void
     */
    private function seedVisibilitySettings(
        bool $openAccess = false,
        bool $strictGroups = true,
        bool $assignmentEnabled = false,
        int $illGroupId = 999,
    ): void {
        $this->seedSettings([
            'requests_visibility_open_access'  => ['value' => (int) $openAccess, 'type' => 'boolean'],
            'requests_visibility_strict_groups' => ['value' => (int) $strictGroups, 'type' => 'boolean'],
            'assignment_enabled'               => ['value' => (int) $assignmentEnabled, 'type' => 'boolean'],
            'ill_selector_group_id'            => ['value' => $illGroupId, 'type' => 'integer'],
        ]);
    }

    /**
     * Insert a user into sfp_users.
     *
     * @param int    $id
     * @param string $role
     * @param string $name
     * @return User
     */
    private function createUser(int $id, string $role = 'selector', string $name = 'User'): User
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('sfp_users')->insert([
            'id'         => $id,
            'name'       => $name,
            'email'      => strtolower($name) . $id . '@example.com',
            'role'       => $role,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::findOrFail($id);
    }

    /**
     * Add a user to one or more selector groups.
     *
     * @param int   $userId
     * @param int[] $groupIds
     * @return void
     */
    private function addToGroups(int $userId, array $groupIds): void
    {
        foreach ($groupIds as $gid) {
            Capsule::table('selector_group_user')->insert([
                'selector_group_id' => $gid,
                'user_id'           => $userId,
            ]);
        }
    }

    /**
     * Create a selector group with its material type and audience pivots.
     *
     * @param int   $id
     * @param int[] $materialTypeIds
     * @param int[] $audienceIds
     * @return void
     */
    private function createGroup(int $id, array $materialTypeIds, array $audienceIds): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('selector_groups')->insert([
            'id'         => $id,
            'name'       => "Group {$id}",
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($materialTypeIds as $mt) {
            Capsule::table('selector_group_material_type')->insert([
                'selector_group_id' => $id,
                'material_type_id'  => $mt,
            ]);
        }

        foreach ($audienceIds as $aud) {
            Capsule::table('selector_group_audience')->insert([
                'selector_group_id' => $id,
                'audience_id'       => $aud,
            ]);
        }
    }

    /**
     * Insert a request row and return its ID.
     *
     * @param string   $kind
     * @param int|null $materialTypeId
     * @param int|null $audienceId
     * @param int|null $assignedTo
     * @return int
     */
    private function insertRequest(
        string $kind = 'sfp',
        ?int $materialTypeId = null,
        ?int $audienceId = null,
        ?int $assignedTo = null,
    ): int {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'request_kind'        => $kind,
            'material_type_id'    => $materialTypeId,
            'audience_id'         => $audienceId,
            'assigned_to_user_id' => $assignedTo,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    /**
     * Get the list of visible request IDs for the given user.
     *
     * @param User|null $user
     * @return int[]
     */
    private function visibleIds(?User $user): array
    {
        return SfpRequest::query()
            ->visibleTo($user)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    // =========================================================================
    // 1. Null user — Decision-tree step 1
    // =========================================================================

    #[Test]
    public function null_user_sees_nothing(): void
    {
        $this->seedVisibilitySettings(openAccess: true);
        $this->insertRequest('sfp', 1, 10);
        $this->insertRequest('ill');

        $this->assertSame([], $this->visibleIds(null));
    }

    // =========================================================================
    // 2. Admin — Decision-tree step 3
    // =========================================================================

    #[Test]
    public function admin_sees_all_requests_regardless_of_settings(): void
    {
        $this->seedVisibilitySettings(openAccess: false, strictGroups: true);

        $admin = $this->createUser(1, 'admin');
        $sfp   = $this->insertRequest('sfp', 1, 10);
        $ill   = $this->insertRequest('ill');

        $this->assertSame([$sfp, $ill], $this->visibleIds($admin));
    }

    // =========================================================================
    // 3. Open access — Decision-tree step 4
    // =========================================================================

    #[Test]
    public function open_access_lets_any_selector_see_all_requests(): void
    {
        $this->seedVisibilitySettings(openAccess: true);

        $selector = $this->createUser(1);
        $sfp      = $this->insertRequest('sfp', 1, 10);
        $ill      = $this->insertRequest('ill');

        $this->assertSame([$sfp, $ill], $this->visibleIds($selector));
    }

    // =========================================================================
    // 4. Strict groups OFF — Decision-tree step 6b (non-strict)
    // =========================================================================

    #[Test]
    public function non_strict_mode_shows_all_sfp_but_still_gates_ill(): void
    {
        $this->seedVisibilitySettings(openAccess: false, strictGroups: false);

        $selector = $this->createUser(1);
        $sfp1     = $this->insertRequest('sfp', 1, 10);
        $sfp2     = $this->insertRequest('sfp', 2, 20);
        $ill      = $this->insertRequest('ill');

        $visible = $this->visibleIds($selector);

        $this->assertContains($sfp1, $visible);
        $this->assertContains($sfp2, $visible);
        $this->assertNotContains($ill, $visible);
    }

    // =========================================================================
    // 5. Strict groups ON — group pairing
    // =========================================================================

    #[Test]
    public function strict_groups_shows_only_matching_pairs(): void
    {
        $this->seedVisibilitySettings(strictGroups: true);

        // Group 100: Book(1) × Adult(10)
        $this->createGroup(100, [1], [10]);

        $selector = $this->createUser(1);
        $this->addToGroups(1, [100]);

        $match   = $this->insertRequest('sfp', 1, 10);
        $noMatch = $this->insertRequest('sfp', 2, 20);

        $this->assertSame([$match], $this->visibleIds($selector));
    }

    #[Test]
    public function groups_do_not_bridge_across_each_other(): void
    {
        $this->seedVisibilitySettings(strictGroups: true);

        // Group 100: Book(1) × Adult(10)
        // Group 200: DVD(2) × Kids(20)
        $this->createGroup(100, [1], [10]);
        $this->createGroup(200, [2], [20]);

        $selector = $this->createUser(1);
        $this->addToGroups(1, [100, 200]);

        $inScopeA = $this->insertRequest('sfp', 1, 10);
        $inScopeB = $this->insertRequest('sfp', 2, 20);
        $bridged1 = $this->insertRequest('sfp', 1, 20); // Book × Kids — no group covers this
        $bridged2 = $this->insertRequest('sfp', 2, 10); // DVD × Adult — no group covers this

        $visible = $this->visibleIds($selector);

        $this->assertSame([$inScopeA, $inScopeB], $visible);
        $this->assertNotContains($bridged1, $visible);
        $this->assertNotContains($bridged2, $visible);
    }

    #[Test]
    public function ungrouped_selector_sees_nothing_in_strict_mode(): void
    {
        $this->seedVisibilitySettings(strictGroups: true);

        $selector = $this->createUser(1);

        $this->insertRequest('sfp', 1, 10);
        $this->insertRequest('ill');

        $this->assertSame([], $this->visibleIds($selector));
    }

    // =========================================================================
    // 6. ILL gating — Decision-tree step 6a
    // =========================================================================

    #[Test]
    public function ill_member_sees_ill_requests(): void
    {
        $this->seedVisibilitySettings(strictGroups: true, illGroupId: 999);

        $this->createGroup(999, [], []);
        $member = $this->createUser(1);
        $this->addToGroups(1, [999]);

        $ill = $this->insertRequest('ill');

        $this->assertSame([$ill], $this->visibleIds($member));
    }

    #[Test]
    public function non_ill_member_cannot_see_ill_requests(): void
    {
        $this->seedVisibilitySettings(strictGroups: true, illGroupId: 999);

        $nonMember = $this->createUser(1);

        $ill = $this->insertRequest('ill');

        $this->assertNotContains($ill, $this->visibleIds($nonMember));
    }

    #[Test]
    public function ill_group_id_zero_blocks_all_ill_for_selectors(): void
    {
        $this->seedVisibilitySettings(strictGroups: false, illGroupId: 0);

        $selector = $this->createUser(1);

        $sfp = $this->insertRequest('sfp', 1, 10);
        $ill = $this->insertRequest('ill');

        $visible = $this->visibleIds($selector);

        $this->assertContains($sfp, $visible);
        $this->assertNotContains($ill, $visible);
    }

    // =========================================================================
    // 7. Assignment override — Decision-tree step 5
    // =========================================================================

    #[Test]
    public function assignment_override_lets_assignee_see_blocked_sfp(): void
    {
        $this->seedVisibilitySettings(strictGroups: true, assignmentEnabled: true);

        $selector = $this->createUser(1);
        // No group memberships — strict scoping would block SFP.
        $assigned = $this->insertRequest('sfp', 1, 10, assignedTo: 1);

        $this->assertSame([$assigned], $this->visibleIds($selector));
    }

    #[Test]
    public function assignment_override_lets_assignee_see_ill_without_group(): void
    {
        $this->seedVisibilitySettings(strictGroups: true, assignmentEnabled: true, illGroupId: 999);

        $selector    = $this->createUser(1);
        $assignedIll = $this->insertRequest('ill', assignedTo: 1);
        $otherIll    = $this->insertRequest('ill');

        $visible = $this->visibleIds($selector);

        $this->assertContains($assignedIll, $visible);
        $this->assertNotContains($otherIll, $visible);
    }

    #[Test]
    public function assignment_off_does_not_grant_override(): void
    {
        $this->seedVisibilitySettings(strictGroups: true, assignmentEnabled: false);

        $selector = $this->createUser(1);
        // Assigned but assignment_enabled is off — should not see it.
        $assigned = $this->insertRequest('sfp', 1, 10, assignedTo: 1);

        $this->assertSame([], $this->visibleIds($selector));
    }

    // =========================================================================
    // 8. Combined scenarios — multiple users, mixed access
    // =========================================================================

    #[Test]
    public function two_selectors_in_different_groups_see_different_requests(): void
    {
        $this->seedVisibilitySettings(strictGroups: true);

        $this->createGroup(100, [1], [10]); // Book × Adult
        $this->createGroup(200, [2], [20]); // DVD × Kids

        $alice = $this->createUser(1, name: 'Alice');
        $bob   = $this->createUser(2, name: 'Bob');
        $this->addToGroups(1, [100]);
        $this->addToGroups(2, [200]);

        $bookAdult = $this->insertRequest('sfp', 1, 10);
        $dvdKids   = $this->insertRequest('sfp', 2, 20);

        $this->assertSame([$bookAdult], $this->visibleIds($alice));
        $this->assertSame([$dvdKids], $this->visibleIds($bob));
    }

    #[Test]
    public function ill_and_sfp_scoping_work_together(): void
    {
        $this->seedVisibilitySettings(strictGroups: true, illGroupId: 999);

        $this->createGroup(100, [1], [10]); // Book × Adult
        $this->createGroup(999, [], []);    // ILL group

        $selectorWithIll = $this->createUser(1, name: 'WithILL');
        $selectorNoIll   = $this->createUser(2, name: 'NoILL');
        $this->addToGroups(1, [100, 999]);
        $this->addToGroups(2, [100]);

        $sfp = $this->insertRequest('sfp', 1, 10);
        $ill = $this->insertRequest('ill');

        $this->assertSame([$sfp, $ill], $this->visibleIds($selectorWithIll));
        $this->assertSame([$sfp], $this->visibleIds($selectorNoIll));
    }
}
