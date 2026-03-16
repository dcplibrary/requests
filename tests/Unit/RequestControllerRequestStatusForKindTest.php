<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that RequestController uses RequestStatus::forKind() when
 * retrieving statuses for advancing requests and for the status dropdown.
 */
class RequestControllerRequestStatusForKindTest extends TestCase
{
    private static function controllerPath(): string
    {
        return dirname(__DIR__, 2) . '/src/Http/Controllers/Admin/RequestController.php';
    }

    #[Test]
    public function request_controller_uses_for_kind_when_advancing_status_on_claim(): void
    {
        $content = file_get_contents(self::controllerPath());
        $this->assertStringContainsString(
            '->forKind($patronRequest->request_kind)',
            $content,
            'Advance-on-claim logic should use forKind with request kind.'
        );
    }

    #[Test]
    public function request_controller_uses_for_kind_for_statuses_on_show_page(): void
    {
        $content = file_get_contents(self::controllerPath());
        $this->assertStringContainsString(
            "RequestStatus::active()->forKind(\$patronRequest->request_kind)->get()",
            $content,
            'Show page statuses dropdown should use active()->forKind(request_kind).'
        );
    }

    #[Test]
    public function request_controller_uses_for_kind_for_custom_filter_fields(): void
    {
        $content = file_get_contents(self::controllerPath());
        $this->assertStringContainsString(
            '->forKind($kind)',
            $content,
            'Custom filter fields should use forKind when kind filter is applied.'
        );
    }
}
