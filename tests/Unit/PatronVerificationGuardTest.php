<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Livewire\IllForm;
use Dcplibrary\Requests\Livewire\RequestForm;
use Livewire\Attributes\Locked;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Actions after step 1 must refuse to run unless step 1 (CAPTCHA + patron checks)
 * passed server-side for the same barcode. Calling submit() etc. directly over
 * Livewire used to bypass the CAPTCHA and the Polaris barcode check.
 */
class PatronVerificationGuardTest extends TestCase
{
    /** @var list<string> container bindings added by this test, removed in tearDown */
    private array $addedBindings = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire's addError() reads shared view errors and keeps the bag in its DataStore.
        $app = \Illuminate\Container\Container::getInstance();
        if (! $app->bound('view')) {
            $app->instance('view', new class {
                public function getShared(): array
                {
                    return [];
                }
            });
            $this->addedBindings[] = 'view';
        }
        if (! $app->bound(\Livewire\Mechanisms\DataStore::class)) {
            $app->singleton(\Livewire\Mechanisms\DataStore::class);
            $this->addedBindings[] = \Livewire\Mechanisms\DataStore::class;
        }
    }

    protected function tearDown(): void
    {
        $app = \Illuminate\Container\Container::getInstance();
        foreach ($this->addedBindings as $abstract) {
            $app->offsetUnset($abstract);
        }
        $this->addedBindings = [];

        parent::tearDown();
    }

    public static function actions(): array
    {
        return [
            'sfp submit'           => [RequestForm::class, 'submit', []],
            'sfp accept isbndb'    => [RequestForm::class, 'acceptIsbndbMatch', [0]],
            'sfp skip isbndb'      => [RequestForm::class, 'skipIsbndbMatch', []],
            'sfp accept catalog'   => [RequestForm::class, 'acceptCatalogMatch', ['123']],
            'sfp skip catalog'     => [RequestForm::class, 'skipCatalogMatch', []],
            'sfp proceed as sfp'   => [RequestForm::class, 'proceedAsSfp', []],
            'sfp redirect to ill'  => [RequestForm::class, 'redirectToIll', []],
            'ill submit'           => [IllForm::class, 'submit', []],
            'ill accept isbndb'    => [IllForm::class, 'acceptIsbndbMatch', [0]],
            'ill skip isbndb'      => [IllForm::class, 'skipIsbndbMatch', []],
            'ill accept catalog'   => [IllForm::class, 'acceptCatalogMatch', ['123']],
            'ill skip catalog'     => [IllForm::class, 'skipCatalogMatch', []],
        ];
    }

    private function component(string $class): object
    {
        $c = new $class();
        $c->barcode    = '21234000123456';
        $c->name_first = 'Pat';
        $c->name_last  = 'Ron';
        $c->phone      = '2705551234';
        $c->step       = 3;

        return $c;
    }

    private function callAction(object $c, string $method, array $args): void
    {
        // Parameter types differ slightly between forms; coerce to what the signature wants.
        $params = (new \ReflectionMethod($c, $method))->getParameters();
        foreach ($params as $i => $p) {
            if (! array_key_exists($i, $args)) {
                continue;
            }
            $type = (string) $p->getType();
            $args[$i] = match (ltrim($type, '?')) {
                'int'    => (int) $args[$i],
                'string' => (string) $args[$i],
                default  => $args[$i],
            };
        }
        $c->{$method}(...$args);
    }

    #[Test]
    #[DataProvider('actions')]
    public function action_is_refused_when_step_one_never_passed(string $class, string $method, array $args): void
    {
        $c = $this->component($class);

        $this->callAction($c, $method, $args);

        $this->assertSame(1, $c->step, "$method should send the patron back to step 1");
        $this->assertTrue($c->getErrorBag()->has('barcode'));
        $this->assertFalse($c->patronVerified);
    }

    #[Test]
    #[DataProvider('actions')]
    public function action_is_refused_when_barcode_changed_after_step_one(string $class, string $method, array $args): void
    {
        $c = $this->component($class);
        (new \ReflectionMethod($c, 'markPatronVerified'))->invoke($c);
        $c->barcode = '29999000999999';

        $this->callAction($c, $method, $args);

        $this->assertSame(1, $c->step);
        $this->assertFalse($c->patronVerified);
    }

    #[Test]
    public function verification_state_and_step_are_locked(): void
    {
        foreach ([RequestForm::class, IllForm::class] as $class) {
            foreach (['patronVerified', 'verifiedBarcode', 'step'] as $prop) {
                $attrs = (new \ReflectionProperty($class, $prop))->getAttributes(Locked::class);
                $this->assertNotEmpty($attrs, "$class::\$$prop must be #[Locked]");
            }
        }
    }
}
