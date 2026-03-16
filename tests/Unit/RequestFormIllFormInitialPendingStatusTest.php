<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that RequestForm and IllForm retrieve the initial pending status
 * using applies_to_sfp and applies_to_ill respectively.
 */
class RequestFormIllFormInitialPendingStatusTest extends TestCase
{
    private static function requestFormPath(): string
    {
        return dirname(__DIR__, 2) . '/src/Livewire/RequestForm.php';
    }

    private static function illFormPath(): string
    {
        return dirname(__DIR__, 2) . '/src/Livewire/IllForm.php';
    }

    #[Test]
    public function request_form_retrieves_initial_pending_status_by_applies_to_sfp(): void
    {
        $content = file_get_contents(self::requestFormPath());
        $this->assertStringContainsString(
            "RequestStatus::where('applies_to_sfp', true)->orderBy('sort_order')->first()",
            $content,
            'RequestForm should use applies_to_sfp when resolving initial pending status.'
        );
    }

    #[Test]
    public function ill_form_retrieves_initial_pending_status_by_applies_to_ill(): void
    {
        $content = file_get_contents(self::illFormPath());
        $this->assertStringContainsString(
            "RequestStatus::where('applies_to_ill', true)->orderBy('sort_order')->first()",
            $content,
            'IllForm should use applies_to_ill when resolving initial pending status.'
        );
    }
}
