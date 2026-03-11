<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for PatronRequest::scopeVisibleTo() permission logic.
 *
 * Uses the unified schema: selector_group_field_option pivot, request_field_values
 * EAV table, and fields/field_options lookup tables.
 *
 * Boots a single SQLite in-memory database once, then resets data between tests.
 * No external services or MySQL required — safe for GitHub Actions CI.
 *
 * @see \Dcplibrary\Requests\Models\PatronRequest::scopeVisibleTo()
 * @see docs/permissions.md
 */
class RequestPermissionsTest extends TestCase
{
    /** @var bool Whether the SQLite schema has been created. */
    private static bool $booted = false;

    /** @var int Field ID for 'material_type'. */
    private static int $mtFieldId;

    /** @var int Field ID for 'audience'. */
    private static int $audFieldId;

    /** @var int FieldOption ID for 'book' (material_type). */
    private static int $bookOptId;

    /** @var int FieldOption ID for 'dvd' (material_type). */
    private static int $dvdOptId;

    /** @var int FieldOption ID for 'adult' (audience). */
    private static int $adultOptId;

    /** @var int FieldOption ID for 'kids' (audience). */
    private static int $kidsOptId;

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

        $schema->create('staff_users', function (Blueprint $table) {
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

        $schema->create('selector_group_field_option', function (Blueprint $table) {
            $table->integer('selector_group_id');
            $table->integer('field_option_id');
        });

        $schema->create('fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('select');
            $table->integer('step')->default(2);
            $table->string('scope')->default('both');
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
            $table->text('metadata')->nullable();
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

        // Seed lookup data: material_type + audience fields and their options.
        $now = date('Y-m-d H:i:s');

        Capsule::table('fields')->insert([
            ['key' => 'material_type', 'label' => 'Material Type', 'type' => 'select', 'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'audience', 'label' => 'Audience', 'type' => 'select', 'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        self::$mtFieldId  = (int) Capsule::table('fields')->where('key', 'material_type')->value('id');
        self::$audFieldId = (int) Capsule::table('fields')->where('key', 'audience')->value('id');

        Capsule::table('field_options')->insert([
            ['field_id' => self::$mtFieldId,  'name' => 'Book',  'slug' => 'book',  'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => self::$mtFieldId,  'name' => 'DVD',   'slug' => 'dvd',   'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => self::$audFieldId, 'name' => 'Adult', 'slug' => 'adult', 'sort_order' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['field_id' => self::$audFieldId, 'name' => 'Kids',  'slug' => 'kids',  'sort_order' => 2, 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        self::$bookOptId  = (int) Capsule::table('field_options')->where('slug', 'book')->value('id');
        self::$dvdOptId   = (int) Capsule::table('field_options')->where('slug', 'dvd')->value('id');
        self::$adultOptId = (int) Capsule::table('field_options')->where('slug', 'adult')->value('id');
        self::$kidsOptId  = (int) Capsule::table('field_options')->where('slug', 'kids')->value('id');

        self::$booted = true;
    }

    /**
     * Truncate transactional tables and flush the cache between tests.
     *
     * @return void
     */
    private function resetData(): void
    {
        Capsule::table('requests')->delete();
        Capsule::table('request_field_values')->delete();
        Capsule::table('staff_users')->delete();
        Capsule::table('selector_groups')->delete();
        Capsule::table('selector_group_user')->delete();
        Capsule::table('selector_group_field_option')->delete();
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
     * Insert a user into staff_users.
     *
     * @param int    $id
     * @param string $role
     * @param string $name
     * @return User
     */
    private function createUser(int $id, string $role = 'selector', string $name = 'User'): User
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('staff_users')->insert([
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
     * Create a selector group with its field option pivots.
     *
     * @param int   $id
     * @param int[] $fieldOptionIds  IDs from the field_options table
     * @return void
     */
    private function createGroup(int $id, array $fieldOptionIds): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('selector_groups')->insert([
            'id'         => $id,
            'name'       => "Group {$id}",
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($fieldOptionIds as $optId) {
            Capsule::table('selector_group_field_option')->insert([
                'selector_group_id' => $id,
                'field_option_id'   => $optId,
            ]);
        }
    }

    /**
     * Insert a request row with optional EAV field values.
     *
     * @param string      $kind
     * @param string|null $materialTypeSlug  Stored in request_field_values
     * @param string|null $audienceSlug      Stored in request_field_values
     * @param int|null    $assignedTo
     * @return int
     */
    private function insertRequest(
        string $kind = 'sfp',
        ?string $materialTypeSlug = null,
        ?string $audienceSlug = null,
        ?int $assignedTo = null,
    ): int {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'request_kind'        => $kind,
            'assigned_to_user_id' => $assignedTo,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $requestId = (int) Capsule::connection()->getPdo()->lastInsertId();

        if ($materialTypeSlug !== null) {
            Capsule::table('request_field_values')->insert([
                'request_id' => $requestId,
                'field_id'   => self::$mtFieldId,
                'value'      => $materialTypeSlug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($audienceSlug !== null) {
            Capsule::table('request_field_values')->insert([
                'request_id' => $requestId,
                'field_id'   => self::$audFieldId,
                'value'      => $audienceSlug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $requestId;
    }

    /**
     * Get the list of visible request IDs for the given user.
     *
     * @param User|null $user
     * @return int[]
     */
    private function visibleIds(?User $user): array
    {
        return PatronRequest::query()
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
        $this->insertRequest('sfp', 'book', 'adult');
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
        $sfp   = $this->insertRequest('sfp', 'book', 'adult');
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
        $sfp      = $this->insertRequest('sfp', 'book', 'adult');
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
        $sfp1     = $this->insertRequest('sfp', 'book', 'adult');
        $sfp2     = $this->insertRequest('sfp', 'dvd', 'kids');
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

        // Group 100: Book × Adult
        $this->createGroup(100, [self::$bookOptId, self::$adultOptId]);

        $selector = $this->createUser(1);
        $this->addToGroups(1, [100]);

        $match   = $this->insertRequest('sfp', 'book', 'adult');
        $noMatch = $this->insertRequest('sfp', 'dvd', 'kids');

        $this->assertSame([$match], $this->visibleIds($selector));
    }

    #[Test]
    public function groups_do_not_bridge_across_each_other(): void
    {
        $this->seedVisibilitySettings(strictGroups: true);

        // Group 100: Book × Adult
        // Group 200: DVD × Kids
        $this->createGroup(100, [self::$bookOptId, self::$adultOptId]);
        $this->createGroup(200, [self::$dvdOptId, self::$kidsOptId]);

        $selector = $this->createUser(1);
        $this->addToGroups(1, [100, 200]);

        $inScopeA = $this->insertRequest('sfp', 'book', 'adult');
        $inScopeB = $this->insertRequest('sfp', 'dvd', 'kids');
        $bridged1 = $this->insertRequest('sfp', 'book', 'kids');   // Book × Kids — no group covers this
        $bridged2 = $this->insertRequest('sfp', 'dvd', 'adult');   // DVD × Adult — no group covers this

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

        $this->insertRequest('sfp', 'book', 'adult');
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

        $this->createGroup(999, []);
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

        $sfp = $this->insertRequest('sfp', 'book', 'adult');
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
        $assigned = $this->insertRequest('sfp', 'book', 'adult', assignedTo: 1);

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
        $assigned = $this->insertRequest('sfp', 'book', 'adult', assignedTo: 1);

        $this->assertSame([], $this->visibleIds($selector));
    }

    // =========================================================================
    // 8. Combined scenarios — multiple users, mixed access
    // =========================================================================

    #[Test]
    public function two_selectors_in_different_groups_see_different_requests(): void
    {
        $this->seedVisibilitySettings(strictGroups: true);

        $this->createGroup(100, [self::$bookOptId, self::$adultOptId]); // Book × Adult
        $this->createGroup(200, [self::$dvdOptId, self::$kidsOptId]);   // DVD × Kids

        $alice = $this->createUser(1, name: 'Alice');
        $bob   = $this->createUser(2, name: 'Bob');
        $this->addToGroups(1, [100]);
        $this->addToGroups(2, [200]);

        $bookAdult = $this->insertRequest('sfp', 'book', 'adult');
        $dvdKids   = $this->insertRequest('sfp', 'dvd', 'kids');

        $this->assertSame([$bookAdult], $this->visibleIds($alice));
        $this->assertSame([$dvdKids], $this->visibleIds($bob));
    }

    #[Test]
    public function ill_and_sfp_scoping_work_together(): void
    {
        $this->seedVisibilitySettings(strictGroups: true, illGroupId: 999);

        $this->createGroup(100, [self::$bookOptId, self::$adultOptId]); // Book × Adult
        $this->createGroup(999, []);                                    // ILL group

        $selectorWithIll = $this->createUser(1, name: 'WithILL');
        $selectorNoIll   = $this->createUser(2, name: 'NoILL');
        $this->addToGroups(1, [100, 999]);
        $this->addToGroups(2, [100]);

        $sfp = $this->insertRequest('sfp', 'book', 'adult');
        $ill = $this->insertRequest('ill');

        $this->assertSame([$sfp, $ill], $this->visibleIds($selectorWithIll));
        $this->assertSame([$sfp], $this->visibleIds($selectorNoIll));
    }
}
