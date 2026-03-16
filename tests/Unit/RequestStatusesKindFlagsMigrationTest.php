<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the migration that adds applies_to_sfp and applies_to_ill
 * to the request_statuses table.
 */
class RequestStatusesKindFlagsMigrationTest extends TestCase
{
    private static function migrationPath(): string
    {
        return dirname(__DIR__, 2) . '/database/migrations/2026_03_16_000004_add_kind_flags_to_request_statuses_table.php';
    }

    #[Test]
    public function migration_adds_applies_to_sfp_and_applies_to_ill_to_request_statuses_table(): void
    {
        $path = self::migrationPath();
        $this->assertFileExists($path, 'Migration file should exist.');

        $content = file_get_contents($path);
        $this->assertStringContainsString("'request_statuses'", $content, 'Migration should target request_statuses table.');

        $this->assertStringContainsString('applies_to_sfp', $content, 'Migration should add applies_to_sfp column.');
        $this->assertStringContainsString('applies_to_ill', $content, 'Migration should add applies_to_ill column.');
        $this->assertStringContainsString('boolean', $content, 'Columns should be boolean type.');
        $this->assertStringContainsString('default(true)', $content, 'Columns should default to true.');
    }

    #[Test]
    public function migration_down_removes_kind_flag_columns(): void
    {
        $content = file_get_contents(self::migrationPath());
        $this->assertStringContainsString('dropColumn', $content, 'Down migration should drop columns.');
        $this->assertStringContainsString('applies_to_sfp', $content);
        $this->assertStringContainsString('applies_to_ill', $content);
    }
}
