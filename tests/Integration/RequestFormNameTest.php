<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\PatronRequest;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for request_form_name() helper.
 *
 * Verifies that the helper returns the Form model's name for known slugs
 * and falls back to the slug when the form is not found.
 */
class RequestFormNameTest extends TestCase
{
    /** @var bool */
    private static bool $booted = false;

    private function bootDatabase(): void
    {
        if (self::$booted) {
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->schema();
        $schema->create('forms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $now = date('Y-m-d H:i:s');
        Capsule::table('forms')->insert([
            ['name' => 'Suggest for Purchase', 'slug' => PatronRequest::KIND_SFP, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Interlibrary Loan', 'slug' => PatronRequest::KIND_ILL, 'created_at' => $now, 'updated_at' => $now],
        ]);

        self::$booted = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
    }

    #[Test]
    public function request_form_name_returns_form_name_for_sfp_slug(): void
    {
        $this->assertSame('Suggest for Purchase', request_form_name(PatronRequest::KIND_SFP));
    }

    #[Test]
    public function request_form_name_returns_form_name_for_ill_slug(): void
    {
        $this->assertSame('Interlibrary Loan', request_form_name(PatronRequest::KIND_ILL));
    }

    #[Test]
    public function request_form_name_falls_back_to_slug_when_form_not_found(): void
    {
        $this->assertSame('unknown', request_form_name('unknown'));
    }
}
