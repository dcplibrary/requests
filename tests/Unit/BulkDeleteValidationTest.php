<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that bulk delete and bulk operations validate input correctly
 * (ids required, array, min:1; ids.* integer and exists).
 */
class BulkDeleteValidationTest extends TestCase
{
    private static function controllerPath(): string
    {
        return dirname(__DIR__, 2) . '/src/Http/Controllers/Admin/RequestController.php';
    }

    #[Test]
    public function bulk_delete_validates_ids_required_and_min_one(): void
    {
        $content = file_get_contents(self::controllerPath());
        $this->assertStringContainsString("'ids'   => 'required|array|min:1'", $content);
        $this->assertStringContainsString("'ids.*' => 'integer|exists:requests,id'", $content);
    }

    #[Test]
    public function bulk_delete_handles_no_requests_selected_via_validation(): void
    {
        $content = file_get_contents(self::controllerPath());
        $this->assertStringContainsString("'ids'   => 'required|array|min:1'", $content, 'Empty ids array fails validation (min:1).');
    }

    #[Test]
    public function bulk_delete_validates_ids_are_integers_and_exist(): void
    {
        $content = file_get_contents(self::controllerPath());
        $this->assertStringContainsString('exists:requests,id', $content, 'Bulk delete should validate ids exist in requests table.');
        $this->assertStringContainsString('integer', $content, 'Bulk delete should validate each id is integer.');
    }

    #[Test]
    public function bulk_status_validates_ids_and_status_id(): void
    {
        $content = file_get_contents(self::controllerPath());
        $this->assertStringContainsString("'ids'       => 'required|array|min:1'", $content);
        $this->assertStringContainsString("'ids.*'     => 'integer|exists:requests,id'", $content);
        $this->assertStringContainsString("'status_id' => 'required|integer|exists:request_statuses,id'", $content);
    }
}
