<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Http\Controllers\Admin\RequestController;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Integration tests for RequestController::emailAction().
 *
 * Covers invalid signature, missing/invalid status_id, and valid transition (including
 * no-op when request is already at target status).
 */
class RequestControllerEmailActionTest extends TestCase
{
    private static bool $booted = false;
    private static int $statusPendingId;
    private static int $statusUnderReviewId;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

        $schema->create('request_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->default('#6b7280');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('notify_patron')->default(false);
            $table->boolean('applies_to_sfp')->default(true);
            $table->boolean('applies_to_ill')->default(true);
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
            ['name' => 'Pending', 'slug' => 'pending', 'sort_order' => 1, 'active' => 1, 'applies_to_sfp' => 1, 'applies_to_ill' => 1, 'created_at' => $now, 'updated_at' => $now],
            // SFP-only next step (typical split workflow — ILL email links must not target this).
            ['name' => 'Under Review', 'slug' => 'under-review', 'sort_order' => 2, 'active' => 1, 'applies_to_sfp' => 1, 'applies_to_ill' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        self::$statusPendingId      = (int) Capsule::table('request_statuses')->where('slug', 'pending')->value('id');
        self::$statusUnderReviewId = (int) Capsule::table('request_statuses')->where('slug', 'under-review')->value('id');

        self::$booted = true;
    }

    private function resetData(): void
    {
        Capsule::table('request_status_history')->delete();
        Capsule::table('requests')->delete();
        Capsule::table('patrons')->delete();
        Cache::flush();
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

    private function createRequest(int $patronId, int $statusId, string $kind = 'sfp'): PatronRequest
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'patron_id'         => $patronId,
            'request_status_id' => $statusId,
            'request_kind'      => $kind,
            'submitted_title'   => 'Test Title',
            'submitted_author'  => 'Test Author',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
        $requestId = (int) Capsule::connection()->getPdo()->lastInsertId();
        return PatronRequest::findOrFail($requestId);
    }

    private function mockRequest(bool $validSignature, $statusId = null): Request
    {
        $request = Mockery::mock(Request::class)->makePartial();
        $request->shouldReceive('hasValidSignature')->andReturn($validSignature);
        $request->shouldReceive('query')->with('status_id')->andReturn($statusId);
        return $request;
    }

    #[Test]
    public function email_action_handles_invalid_signature(): void
    {
        $request = $this->mockRequest(false, '1');
        $patronRequest = Mockery::mock(PatronRequest::class)->makePartial();
        $patronRequest->id = 1;

        $controller = new RequestController();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('This link has expired or is invalid.');

        try {
            $controller->emailAction($request, $patronRequest);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    #[Test]
    public function email_action_handles_missing_status_id(): void
    {
        $this->bootDatabase();
        $this->resetData();
        $this->createPatron(1);
        $patronRequest = $this->createRequest(1, self::$statusPendingId);

        $request = $this->mockRequest(true, null);
        $controller = new RequestController();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Missing status_id.');

        try {
            $controller->emailAction($request, $patronRequest);
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
            throw $e;
        }
    }

    #[Test]
    public function email_action_rejects_status_not_in_workflow_for_ill_requests(): void
    {
        $this->bootDatabase();
        $this->resetData();
        $this->createPatron(1);
        $patronRequest = $this->createRequest(1, self::$statusPendingId, 'ill');

        $request = $this->mockRequest(true, (string) self::$statusUnderReviewId);
        $controller = new RequestController();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('not part of the workflow');

        try {
            $controller->emailAction($request, $patronRequest->fresh());
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    #[Test]
    public function email_action_handles_status_id_that_does_not_exist(): void
    {
        $this->bootDatabase();
        $this->resetData();
        $this->createPatron(1);
        $patronRequest = $this->createRequest(1, self::$statusPendingId);

        $request = $this->mockRequest(true, '99999');
        $controller = new RequestController();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Status not found.');

        try {
            $controller->emailAction($request, $patronRequest);
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            throw $e;
        }
    }

    #[Test]
    public function email_action_transitions_status_when_valid_link_clicked(): void
    {
        $this->bootDatabase();
        $this->resetData();
        $this->createPatron(1);
        $patronRequest = $this->createRequest(1, self::$statusPendingId);

        $notificationMock = Mockery::mock(NotificationService::class);
        $notificationMock->shouldReceive('notifyPatronStatusChange')->once()->andReturn(true);
        app()->instance(NotificationService::class, $notificationMock);

        $request = $this->mockRequest(true, (string) self::$statusUnderReviewId);
        $controller = new RequestController();

        $response = $controller->emailAction($request, $patronRequest->fresh());

        $patronRequest->refresh();
        $patronRequest->load('status');
        $this->assertSame(self::$statusUnderReviewId, $patronRequest->request_status_id);
        $this->assertSame('under-review', $patronRequest->status->slug);

        $history = $patronRequest->statusHistory()->orderBy('id')->get();
        $this->assertCount(1, $history);
        $this->assertSame(self::$statusUnderReviewId, $history[0]->request_status_id);
        $this->assertStringContainsString('email action link', $history[0]->note ?? '');
    }

    #[Test]
    public function email_action_does_not_transition_when_request_already_at_target_status(): void
    {
        $this->bootDatabase();
        $this->resetData();
        $this->createPatron(1);
        $patronRequest = $this->createRequest(1, self::$statusPendingId);

        $notificationMock = Mockery::mock(NotificationService::class);
        $notificationMock->shouldNotReceive('notifyPatronStatusChange');
        app()->instance(NotificationService::class, $notificationMock);

        $request = $this->mockRequest(true, (string) self::$statusPendingId);
        $controller = new RequestController();

        $controller->emailAction($request, $patronRequest->fresh());

        $patronRequest->refresh();
        $this->assertSame(self::$statusPendingId, $patronRequest->request_status_id);

        $history = $patronRequest->statusHistory()->get();
        $this->assertCount(0, $history, 'No status history should be created when already at target status.');
    }
}
