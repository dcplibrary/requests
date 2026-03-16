<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\RequestStatusHistory;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for patron request assignment and unclaimed→pending status transition.
 *
 * Covers the behavior in RequestController::show(): auto-claim when assignment is enabled
 * and the request is unclaimed, and advancing status from unclaimed to pending when assigned.
 *
 * Uses in-memory SQLite; no Laravel HTTP stack required.
 */
class PatronRequestAssignmentTest extends TestCase
{
    private static bool $booted = false;

    private static int $unclaimedStatusId;
    private static int $pendingStatusId;

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

        $schema->create('request_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->default('#6b7280');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('notify_patron')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $schema->create('patrons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('barcode')->unique();
            $table->string('name_first');
            $table->string('name_last');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('patron_id');
            $table->unsignedInteger('request_status_id');
            $table->string('request_kind', 20)->default('sfp');
            $table->unsignedInteger('assigned_to_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedInteger('assigned_by_user_id')->nullable();
            $table->string('submitted_title');
            $table->string('submitted_author');
            $table->timestamps();
        });

        $schema->create('request_status_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('request_status_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        $now = date('Y-m-d H:i:s');
        Capsule::table('request_statuses')->insert([
            ['name' => 'Unclaimed', 'slug' => 'unclaimed', 'color' => '#9ca3af', 'sort_order' => 0, 'active' => 1, 'is_terminal' => 0, 'notify_patron' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pending', 'slug' => 'pending', 'color' => '#f59e0b', 'sort_order' => 1, 'active' => 1, 'is_terminal' => 0, 'notify_patron' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        self::$unclaimedStatusId = (int) Capsule::table('request_statuses')->where('slug', 'unclaimed')->value('id');
        self::$pendingStatusId   = (int) Capsule::table('request_statuses')->where('slug', 'pending')->value('id');

        self::$booted = true;
    }

    private function resetData(): void
    {
        Capsule::table('request_status_history')->delete();
        Capsule::table('requests')->delete();
        Capsule::table('patrons')->delete();
        Capsule::table('staff_users')->delete();
        Capsule::table('settings')->delete();
        Cache::flush();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        $this->resetData();
    }

    private function seedAssignmentEnabled(bool $enabled = true): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('settings')->insert([
            'key'        => 'assignment_enabled',
            'value'      => $enabled ? '1' : '0',
            'type'       => 'boolean',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Cache::flush();
    }

    private function createStaffUser(int $id, string $name = 'Staff'): User
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('staff_users')->insert([
            'id'         => $id,
            'name'       => $name,
            'email'      => strtolower($name) . $id . '@example.com',
            'role'       => 'selector',
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return User::findOrFail($id);
    }

    private function createPatron(int $id = 1): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('patrons')->insert([
            'id'         => $id,
            'barcode'    => 'P' . $id,
            'name_first' => 'Patron',
            'name_last'  => 'User',
            'phone'      => '555-0000',
            'email'      => 'patron' . $id . '@example.com',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $id;
    }

    /**
     * Create a patron request with the given status and assignment.
     */
    private function createRequest(
        int $patronId,
        string $statusSlug = 'unclaimed',
        ?int $assignedToUserId = null,
    ): PatronRequest {
        $statusId = $statusSlug === 'pending' ? self::$pendingStatusId : self::$unclaimedStatusId;
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'patron_id'             => $patronId,
            'request_status_id'     => $statusId,
            'request_kind'          => 'sfp',
            'assigned_to_user_id'   => $assignedToUserId,
            'assigned_at'           => $assignedToUserId ? $now : null,
            'assigned_by_user_id'   => $assignedToUserId,
            'submitted_title'       => 'Test Title',
            'submitted_author'      => 'Test Author',
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
        $requestId = (int) Capsule::connection()->getPdo()->lastInsertId();
        return PatronRequest::findOrFail($requestId);
    }

    /**
     * Apply the same auto-claim and unclaimed→pending logic as RequestController::show().
     */
    private function applyShowAssignmentLogic(PatronRequest $patronRequest, ?User $actor, bool $noclaim = false): void
    {
        $assignmentEnabled = (bool) Setting::get('assignment_enabled', false);
        $justClaimed = false;

        if ($assignmentEnabled && ! $patronRequest->assigned_to_user_id && ! $noclaim && $actor) {
            $patronRequest->update([
                'assigned_to_user_id' => $actor->id,
                'assigned_at'         => now(),
                'assigned_by_user_id' => $actor->id,
            ]);
            $patronRequest->statusHistory()->create([
                'request_status_id' => $patronRequest->request_status_id,
                'user_id'           => $actor->id,
                'note'              => 'Auto-claimed on open.',
            ]);
            $patronRequest->load(['assignedTo', 'assignedBy', 'status', 'statusHistory.status', 'statusHistory.user']);
            $justClaimed = true;
        }

        if ($patronRequest->assigned_to_user_id
            && strtolower($patronRequest->status?->slug ?? '') === 'unclaimed'
        ) {
            $pendingStatus = RequestStatus::where('slug', 'pending')->first();
            if ($pendingStatus) {
                $actor = $actor ?? null;
                $patronRequest->transitionStatus($pendingStatus->id, $actor?->id, 'Status advanced from Unclaimed on open.');
                $patronRequest->load(['status', 'statusHistory.status', 'statusHistory.user']);
            }
        }
    }

    #[Test]
    public function patron_request_is_assigned_to_current_staff_user_when_assignment_enabled_and_request_unclaimed(): void
    {
        $this->seedAssignmentEnabled(true);
        $this->createPatron(1);
        $staff = $this->createStaffUser(1, 'Alice');
        $request = $this->createRequest(1, 'unclaimed', null);

        $this->assertNull($request->assigned_to_user_id);

        $this->applyShowAssignmentLogic($request->fresh(), $staff);

        $request->refresh();
        $this->assertSame($staff->id, $request->assigned_to_user_id);
        $this->assertNotNull($request->assigned_at);
        $this->assertSame($staff->id, $request->assigned_by_user_id);
    }

    #[Test]
    public function patron_request_status_transitions_from_unclaimed_to_pending_when_assigned(): void
    {
        $this->seedAssignmentEnabled(true);
        $this->createPatron(1);
        $staff = $this->createStaffUser(1, 'Bob');
        $request = $this->createRequest(1, 'unclaimed', null);

        $this->assertSame('unclaimed', $request->status->slug);

        $this->applyShowAssignmentLogic($request->fresh(), $staff);

        $request->refresh();
        $request->load('status');
        $this->assertSame('pending', $request->status->slug);
    }

    #[Test]
    public function patron_request_status_transitions_from_unclaimed_to_pending_when_already_assigned_and_status_unclaimed(): void
    {
        $this->seedAssignmentEnabled(true);
        $this->createPatron(1);
        $staff = $this->createStaffUser(1, 'Carol');
        $request = $this->createRequest(1, 'unclaimed', $staff->id);

        $this->assertSame($staff->id, $request->assigned_to_user_id);
        $this->assertSame('unclaimed', $request->status->slug);

        $this->applyShowAssignmentLogic($request->fresh(), $staff);

        $request->refresh();
        $request->load('status');
        $this->assertSame('pending', $request->status->slug);
    }

    #[Test]
    public function patron_request_status_history_created_with_correct_status_and_user_when_assigned(): void
    {
        $this->seedAssignmentEnabled(true);
        $this->createPatron(1);
        $staff = $this->createStaffUser(1, 'Dana');
        $request = $this->createRequest(1, 'unclaimed', null);

        $this->applyShowAssignmentLogic($request->fresh(), $staff);

        $request->refresh();
        $history = $request->statusHistory()->with(['status', 'user'])->orderBy('id')->get();

        $this->assertCount(2, $history, 'Expected auto-claim entry and unclaimed→pending transition entry.');

        $autoClaim = $history[0];
        $this->assertSame(self::$unclaimedStatusId, $autoClaim->request_status_id);
        $this->assertSame($staff->id, $autoClaim->user_id);
        $this->assertSame('unclaimed', $autoClaim->status->slug);
        $this->assertStringContainsString('Auto-claimed', $autoClaim->note ?? '');

        $transition = $history[1];
        $this->assertSame(self::$pendingStatusId, $transition->request_status_id);
        $this->assertSame($staff->id, $transition->user_id);
        $this->assertSame('pending', $transition->status->slug);
        $this->assertStringContainsString('Unclaimed on open', $transition->note ?? '');
    }
}
