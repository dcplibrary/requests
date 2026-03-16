<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\Patron;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for notify_by_email: persistence on PatronRequest and
 * NotificationService only sending when notify_by_email is true and email exists.
 */
class NotifyByEmailIntegrationTest extends TestCase
{
    private static bool $booted = false;
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

        $schema->create('request_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('notify_patron')->default(false);
            $table->timestamps();
        });

        $schema->create('patrons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('barcode')->unique();
            $table->string('name_first');
            $table->string('name_last');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('preferred_email')->default('submitted');
            $table->timestamps();
        });

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('patron_id');
            $table->unsignedInteger('request_status_id');
            $table->string('request_kind', 20)->default('sfp');
            $table->string('submitted_title');
            $table->string('submitted_author');
            $table->boolean('notify_by_email')->default(false);
            $table->timestamps();
        });

        $schema->create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        $schema->create('fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->string('scope')->default('sfp');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('include_as_token')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('request_field_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('field_id');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $schema->create('patron_status_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('subject');
            $table->text('body')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $schema->create('patron_status_template_request_status', function (Blueprint $table) {
            $table->unsignedInteger('patron_status_template_id');
            $table->unsignedInteger('request_status_id');
            $table->primary(['patron_status_template_id', 'request_status_id']);
        });

        $now = date('Y-m-d H:i:s');
        Capsule::table('request_statuses')->insert([
            ['name' => 'Pending', 'slug' => 'pending', 'notify_patron' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        self::$pendingStatusId = (int) Capsule::table('request_statuses')->where('slug', 'pending')->value('id');

        self::$booted = true;
    }

    private function seedSettings(bool $notificationsEnabled = true, bool $patronStatusEnabled = true): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('settings')->insert([
            ['key' => 'notifications_enabled', 'value' => $notificationsEnabled ? '1' : '0', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'patron_status_notification_enabled', 'value' => $patronStatusEnabled ? '1' : '0', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
        ]);
        Cache::flush();
    }

    private function resetData(): void
    {
        Capsule::table('patron_status_template_request_status')->delete();
        Capsule::table('patron_status_templates')->delete();
        Capsule::table('request_field_values')->delete();
        Capsule::table('requests')->delete();
        Capsule::table('patrons')->delete();
        Capsule::table('settings')->delete();
        Cache::flush();
    }

    private function createPatron(int $id, ?string $email = 'patron@example.com'): Patron
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('patrons')->insert([
            'id'              => $id,
            'barcode'         => 'P' . $id,
            'name_first'      => 'Patron',
            'name_last'       => 'User',
            'phone'           => '555-0000',
            'email'           => $email,
            'preferred_email' => 'submitted',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        return Patron::findOrFail($id);
    }

    private function createRequest(int $patronId, bool $notifyByEmail): PatronRequest
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'patron_id'         => $patronId,
            'request_status_id' => self::$pendingStatusId,
            'request_kind'      => 'sfp',
            'submitted_title'   => 'Test',
            'submitted_author'  => 'Author',
            'notify_by_email'   => $notifyByEmail,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
        $requestId = (int) Capsule::connection()->getPdo()->lastInsertId();
        return PatronRequest::findOrFail($requestId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        $this->resetData();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function notify_by_email_field_is_saved_and_loaded_on_patron_request(): void
    {
        $this->createPatron(1);
        $this->createRequest(1, true);

        $request = PatronRequest::first();
        $this->assertTrue($request->notify_by_email, 'notify_by_email should be persisted as true.');
    }

    #[Test]
    public function notify_by_email_false_is_persisted(): void
    {
        $this->createPatron(1);
        $this->createRequest(1, false);

        $request = PatronRequest::first();
        $this->assertFalse($request->notify_by_email);
    }

    #[Test]
    public function notification_service_does_not_send_when_notify_by_email_is_false(): void
    {
        $this->seedSettings(true, true);
        $this->createPatron(1, 'valid@example.com');
        $request = $this->createRequest(1, false);

        $service = new NotificationService();
        $result = $service->notifyPatronStatusChange($request->fresh());

        $this->assertFalse($result, 'Should not send when notify_by_email is false.');
    }

    #[Test]
    public function notification_service_does_not_send_when_notify_by_email_true_but_no_email(): void
    {
        $this->seedSettings(true, true);
        $this->createPatron(1, null);
        $request = $this->createRequest(1, true);

        $service = new NotificationService();
        $result = $service->notifyPatronStatusChange($request->fresh());

        $this->assertFalse($result, 'Should not send when patron has no email.');
    }

    #[Test]
    public function notification_service_sends_only_when_notify_by_email_true_and_valid_email_exists(): void
    {
        $this->seedSettings(true, true);
        $this->createPatron(1, 'valid@example.com');
        $request = $this->createRequest(1, true);

        $mailer = \Mockery::mock();
        $mailer->shouldReceive('to')->andReturnSelf();
        $mailer->shouldReceive('send')->andReturnNull();
        app()->instance('mailer', $mailer);

        $log = \Mockery::mock();
        $log->shouldReceive('error')->andReturnNull();
        app()->instance('log', $log);

        $service = new NotificationService();
        $result = $service->notifyPatronStatusChange($request->fresh());

        $this->assertTrue($result, 'Should send when notify_by_email is true and patron has valid email.');
    }
}
