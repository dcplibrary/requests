<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ensures the migration adds staff_email_quick_action to request_statuses.
 */
class StaffEmailQuickActionMigrationTest extends TestCase
{
    private function migrationPath(): string
    {
        return dirname(__DIR__, 2) . '/database/migrations/2026_03_24_000001_add_staff_email_quick_action_to_request_statuses_table.php';
    }

    #[Test]
    public function migration_adds_staff_email_quick_action_column(): void
    {
        $content = file_get_contents($this->migrationPath());
        $this->assertStringContainsString('request_statuses', $content);
        $this->assertStringContainsString('staff_email_quick_action', $content);
    }
}
