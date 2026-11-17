<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\User;
use Dcplibrary\Requests\Services\NotificationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Staff activity emails are on by default and can be turned off per user.
 */
class StaffNotificationPreferenceTest extends TestCase
{
    #[Test]
    public function notification_emails_are_sent_unless_the_user_opts_out(): void
    {
        $default = new User();
        $this->assertTrue($default->wantsNotificationEmails());

        $optedIn = new User();
        $optedIn->setRawAttributes(['receive_notification_emails' => 1]);
        $this->assertTrue($optedIn->wantsNotificationEmails());

        $optedOut = new User();
        $optedOut->setRawAttributes(['receive_notification_emails' => 0, 'email' => 'optout@dcplibrary.org']);
        $this->assertFalse($optedOut->wantsNotificationEmails());
    }

    #[Test]
    public function assignee_notification_is_skipped_when_the_user_opted_out(): void
    {
        $assignee = new User();
        $assignee->setRawAttributes([
            'name' => 'Opt Out',
            'email' => 'optout@dcplibrary.org',
            'receive_notification_emails' => 0,
        ]);
        $actor = new User();
        $actor->setRawAttributes(['name' => 'Actor', 'email' => 'actor@dcplibrary.org']);

        (new NotificationService())->notifyAssignee(new PatronRequest(), $assignee, $actor);

        $this->assertFalse($assignee->wantsNotificationEmails());
    }

    #[Test]
    public function profile_menu_and_settings_page_expose_the_email_choice(): void
    {
        $root = dirname(__DIR__, 2);

        $layout = file_get_contents($root . '/resources/views/staff/_layout.blade.php');
        $this->assertStringContainsString('request.staff.account.edit', $layout);
        $this->assertStringContainsString('Settings', $layout);

        $partial = file_get_contents($root . '/resources/views/staff/account/_email-preference.blade.php');
        $this->assertStringContainsString('Send notification emails', $partial);
        $this->assertStringContainsString('name="receive_notification_emails"', $partial);
        $this->assertStringContainsString('value="1"', $partial);

        $migration = file_get_contents($root . '/database/migrations/2026_11_17_000001_add_receive_notification_emails_to_staff_users.php');
        $this->assertStringContainsString("default(true)", $migration);
        $this->assertStringContainsString("'staff_users'", $migration);
    }
}
