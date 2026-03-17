<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that the request form view displays the notify_by_email checkbox
 * and binds it to the Livewire component.
 */
class NotifyByEmailFormViewTest extends TestCase
{
    private static function patronStepPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/views/components/patron-step.blade.php';
    }

    #[Test]
    public function notify_by_email_checkbox_is_displayed_on_patron_step(): void
    {
        $content = file_get_contents(self::patronStepPath());

        $this->assertStringContainsString('id="notify_by_email"', $content, 'Checkbox should have id notify_by_email.');
        $this->assertStringContainsString('wire:model="notify_by_email"', $content, 'Checkbox should bind to notify_by_email property.');
        $this->assertStringContainsString('type="checkbox"', $content, 'Element should be a checkbox.');
        $this->assertStringContainsString(
            'Notify me by email when my request is updated',
            $content,
            'Checkbox label should describe email notifications.'
        );
    }
}
