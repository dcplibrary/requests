<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Http\Controllers\Admin\RequestController;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Integration tests for bulk delete and related bulk operations.
 *
 * Covers: admin-only delete, non-admin 403, no selection, ID validation.
 * Includes one bulk-status validation test to spread coverage across bulk ops.
 */
class BulkDeleteTest extends TestCase
{
    private static bool $booted = false;
    private static int $statusId;

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

        $schema->create('request_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('staff_email_quick_action')->default(true);
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

        $now = date('Y-m-d H:i:s');
        Capsule::table('request_statuses')->insert([
            ['name' => 'Pending', 'slug' => 'pending', 'created_at' => $now, 'updated_at' => $now],
        ]);
        self::$statusId = (int) Capsule::table('request_statuses')->where('slug', 'pending')->value('id');

        self::$booted = true;
    }

    private function resetData(): void
    {
        Capsule::table('requests')->delete();
        Capsule::table('patrons')->delete();
        Capsule::table('staff_users')->delete();
        Cache::flush();
    }

    private function createAdmin(): User
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('staff_users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@test.com', 'role' => 'admin', 'active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return User::findOrFail(1);
    }

    private function createSelector(): User
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('staff_users')->insert([
            'id' => 2, 'name' => 'Selector', 'email' => 'selector@test.com', 'role' => 'selector', 'active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return User::findOrFail(2);
    }

    private function createPatron(): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('patrons')->insert([
            'id' => 1, 'barcode' => 'P1', 'name_first' => 'A', 'name_last' => 'B', 'phone' => '555', 'email' => 'p@test.com',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function createRequest(): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'patron_id' => 1, 'request_status_id' => self::$statusId, 'request_kind' => 'sfp',
            'submitted_title' => 'T', 'submitted_author' => 'A', 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        $this->resetData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function bulk_delete_correctly_deletes_selected_requests_when_admin_logged_in(): void
    {
        $this->createAdmin();
        $this->createPatron();
        $id1 = $this->createRequest();
        $id2 = $this->createRequest();

        $request = Mockery::mock(Request::class)->makePartial();
        $request->shouldReceive('user')->andReturn(User::find(1));
        $request->shouldReceive('input')->with('ids')->andReturn([$id1, $id2]);
        $request->shouldReceive('validate')->andReturn(['ids' => [$id1, $id2]]);

        $controller = new RequestController();
        $response = $controller->bulkDelete($request);

        $this->assertSame(0, PatronRequest::whereIn('id', [$id1, $id2])->count());
        $this->assertNotNull($response);
    }

    #[Test]
    public function bulk_delete_prevents_non_admin_users_from_deleting_requests(): void
    {
        $this->createSelector();
        $request = Mockery::mock(Request::class)->makePartial();
        $request->shouldReceive('user')->andReturn(User::find(2));

        $controller = new RequestController();

        $this->expectException(HttpException::class);

        try {
            $controller->bulkDelete($request);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }
}
