<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\User;
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Opted-out staff addresses are dropped from activity email recipient lists.
 * Shared inboxes that are not a staff address are still included.
 */
class StaffNotificationPreferenceTest extends TestCase
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

        $capsule->schema()->create('staff_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('selector');
            $table->boolean('active')->default(true);
            $table->boolean('receive_notification_emails')->default(true);
            $table->timestamps();
        });

        self::$booted = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        Capsule::table('staff_users')->delete();
    }

    #[Test]
    public function opted_out_addresses_are_removed_and_shared_inboxes_remain(): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('staff_users')->insert([
            [
                'name' => 'Opt Out',
                'email' => 'optout@dcplibrary.org',
                'role' => 'selector',
                'active' => 1,
                'receive_notification_emails' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Still On',
                'email' => 'keep@dcplibrary.org',
                'role' => 'selector',
                'active' => 1,
                'receive_notification_emails' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->assertFalse(User::where('email', 'optout@dcplibrary.org')->first()->wantsNotificationEmails());
        $this->assertTrue(User::where('email', 'keep@dcplibrary.org')->first()->wantsNotificationEmails());

        $service = new NotificationService();
        $method = new ReflectionMethod($service, 'deliverableStaffEmails');

        $recipients = $method->invoke($service, [
            'OptOut@dcplibrary.org',
            'keep@dcplibrary.org',
            'selectors@dcplibrary.org',
        ]);

        $this->assertSame(['keep@dcplibrary.org', 'selectors@dcplibrary.org'], $recipients);
    }
}
