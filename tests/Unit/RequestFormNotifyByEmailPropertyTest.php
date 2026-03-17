<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Livewire\IllForm;
use Dcplibrary\Requests\Livewire\RequestForm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that the RequestForm Livewire component has the notify_by_email
 * property and it correctly binds (default value and type).
 */
class RequestFormNotifyByEmailPropertyTest extends TestCase
{
    #[Test]
    public function request_form_has_notify_by_email_property(): void
    {
        $form = new RequestForm();

        $this->assertObjectHasProperty('notify_by_email', $form);
        $this->assertFalse($form->notify_by_email, 'Default should be false.');
        $this->assertIsBool($form->notify_by_email);
    }

    #[Test]
    public function notify_by_email_property_can_be_set(): void
    {
        $form = new RequestForm();
        $form->notify_by_email = true;

        $this->assertTrue($form->notify_by_email);
    }

    #[Test]
    public function ill_form_has_notify_by_email_property(): void
    {
        $form = new IllForm();

        $this->assertObjectHasProperty('notify_by_email', $form);
        $this->assertFalse($form->notify_by_email);
        $this->assertIsBool($form->notify_by_email);
    }
}
