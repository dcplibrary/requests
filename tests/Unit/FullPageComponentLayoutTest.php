<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Livewire\IllForm;
use Dcplibrary\Requests\Livewire\PatronPinLogin;
use Dcplibrary\Requests\Livewire\PatronRequests;
use Dcplibrary\Requests\Livewire\RequestForm;
use Livewire\Attributes\Layout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Components routed as full pages must declare the package layout. Without it
 * Livewire 4 falls back to `layouts::app`, which host apps don't define, and the
 * page 500s ("No hint path defined for [layouts]").
 */
class FullPageComponentLayoutTest extends TestCase
{
    public static function fullPageComponents(): array
    {
        return [
            'sfp form'     => [RequestForm::class],
            'ill form'     => [IllForm::class],
            'my requests'  => [PatronRequests::class],
            'my account'   => [PatronPinLogin::class],
        ];
    }

    #[Test]
    #[DataProvider('fullPageComponents')]
    public function component_declares_the_package_layout(string $class): void
    {
        $attrs = (new \ReflectionClass($class))->getAttributes(Layout::class);

        $this->assertCount(1, $attrs, "$class needs #[Layout('requests::layouts.requests')]");
        $this->assertSame('requests::layouts.requests', $attrs[0]->getArguments()[0]);
    }
}
