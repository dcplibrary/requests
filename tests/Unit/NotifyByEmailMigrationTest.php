<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the notify_by_email migration.
 *
 * Ensures the migration adds the notify_by_email column to the requests table
 * with the expected type and default.
 */
class NotifyByEmailMigrationTest extends TestCase
{
    private static function migrationPath(): string
    {
        return dirname(__DIR__, 2) . '/database/migrations/2026_03_16_000003_add_notify_by_email_to_requests_table.php';
    }

    #[Test]
    public function notify_by_email_column_is_added_to_requests_table(): void
    {
        $path = self::migrationPath();
        $this->assertFileExists($path, 'Migration file should exist.');

        $content = file_get_contents($path);
        $this->assertStringContainsString('notify_by_email', $content, 'Migration should add notify_by_email column.');
        $this->assertStringContainsString('boolean', $content, 'Column should be boolean type.');
        $this->assertStringContainsString('default(false)', $content, 'Column should default to false.');
        $this->assertStringContainsString("'requests'", $content, 'Migration should target requests table.');
    }

    #[Test]
    public function migration_down_removes_notify_by_email_column(): void
    {
        $content = file_get_contents(self::migrationPath());
        $this->assertStringContainsString('dropColumn', $content, 'Down migration should drop the column.');
        $this->assertStringContainsString('notify_by_email', $content, 'Down should drop notify_by_email.');
    }
}
