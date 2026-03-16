<?php

namespace Dcplibrary\Requests\Tests\Integration;

use Dcplibrary\Requests\Models\Patron;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Integration tests for NotificationService token replacement (replacePlaceholders).
 *
 * Ensures recognized tokens are replaced and unrecognized tokens are stripped.
 */
class NotificationServiceReplaceTokensTest extends TestCase
{
    private static bool $booted = false;
    private static int $statusId;

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

        $schema->create('request_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $schema->create('patrons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('barcode')->unique();
            $table->string('name_first');
            $table->string('name_last');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('preferred_email')->default('submitted');
            $table->timestamps();
        });

        $schema->create('materials', function (Blueprint $table) {
            $table->increments('id');
            $table->string('isbn')->nullable();
            $table->string('isbn13')->nullable();
            $table->timestamps();
        });

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('patron_id');
            $table->unsignedInteger('request_status_id');
            $table->unsignedInteger('material_id')->nullable();
            $table->string('request_kind', 20)->default('sfp');
            $table->string('submitted_title');
            $table->string('submitted_author');
            $table->timestamps();
        });

        $schema->create('fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->string('scope')->default('sfp');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('include_as_token')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('request_field_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('field_id');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = date('Y-m-d H:i:s');
        Capsule::table('request_statuses')->insert([
            ['name' => 'Pending', 'slug' => 'pending', 'description' => 'Your request has been received.', 'created_at' => $now, 'updated_at' => $now],
        ]);
        self::$statusId = (int) Capsule::table('request_statuses')->where('slug', 'pending')->value('id');

        self::$booted = true;
    }

    private function resetData(): void
    {
        Capsule::table('request_field_values')->delete();
        Capsule::table('requests')->delete();
        Capsule::table('patrons')->delete();
        Capsule::table('materials')->delete();
        Cache::flush();
    }

    private function createPatron(int $id, string $first = 'Jane', string $last = 'Doe', ?string $email = 'jane@example.com'): Patron
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('patrons')->insert([
            'id'              => $id,
            'barcode'         => 'P' . $id,
            'name_first'      => $first,
            'name_last'       => $last,
            'phone'           => '555-1234',
            'email'           => $email,
            'preferred_email' => 'submitted',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        return Patron::findOrFail($id);
    }

    private function createRequest(int $patronId, string $title = 'Test Book', string $author = 'Test Author', ?int $materialId = null): PatronRequest
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('requests')->insert([
            'patron_id'         => $patronId,
            'request_status_id' => self::$statusId,
            'material_id'       => $materialId,
            'request_kind'     => 'sfp',
            'submitted_title'  => $title,
            'submitted_author' => $author,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        $requestId = (int) Capsule::connection()->getPdo()->lastInsertId();
        return PatronRequest::findOrFail($requestId);
    }

    private function createMaterial(?string $isbn = null, ?string $isbn13 = null): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('materials')->insert([
            'isbn' => $isbn, 'isbn13' => $isbn13, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    private function createIsbnField(): int
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('fields')->insert([
            'key' => 'isbn', 'label' => 'ISBN', 'type' => 'text', 'scope' => 'sfp', 'sort_order' => 0,
            'active' => 1, 'include_as_token' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) Capsule::connection()->getPdo()->lastInsertId();
    }

    private function setRequestFieldValue(int $requestId, int $fieldId, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('request_field_values')->insert([
            'request_id' => $requestId, 'field_id' => $fieldId, 'value' => $value, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function invokeReplacePlaceholders(string $template, PatronRequest $request): string
    {
        $service = new NotificationService();
        $method = new ReflectionMethod($service, 'replacePlaceholders');
        $method->setAccessible(true);
        return $method->invoke($service, $template, $request);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        $this->resetData();
    }

    #[Test]
    public function replace_tokens_correctly_replaces_all_recognized_tokens_in_template(): void
    {
        $this->createPatron(1, 'Jane', 'Doe', 'jane@example.com');
        $request = $this->createRequest(1, 'The Great Book', 'Author Name');

        $template = 'Title: {title}, Author: {author}, Patron: {patron_name}, First: {patron_first_name}.';
        $result = $this->invokeReplacePlaceholders($template, $request->fresh()->load('patron'));

        $this->assertStringContainsString('The Great Book', $result);
        $this->assertStringContainsString('Author Name', $result);
        $this->assertStringContainsString('Jane Doe', $result);
        $this->assertStringContainsString('Jane', $result);
        $this->assertStringNotContainsString('{title}', $result);
        $this->assertStringNotContainsString('{author}', $result);
        $this->assertStringNotContainsString('{patron_name}', $result);
        $this->assertStringNotContainsString('{patron_first_name}', $result);
    }

    #[Test]
    public function replace_tokens_strips_remaining_unrecognized_tokens_from_template(): void
    {
        $this->createPatron(1);
        $request = $this->createRequest(1);

        $template = 'Hello {unknown_token} and {another_fake} world.';
        $result = $this->invokeReplacePlaceholders($template, $request->fresh());

        $this->assertStringNotContainsString('{unknown_token}', $result);
        $this->assertStringNotContainsString('{another_fake}', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('world.', $result);
    }

    #[Test]
    public function isbn_token_replaced_from_material_isbn13_when_available(): void
    {
        $this->createPatron(1);
        $matId = $this->createMaterial(null, '9780123456789');
        $request = $this->createRequest(1, 'T', 'A', $matId);

        $template = 'ISBN: {isbn}';
        $result = $this->invokeReplacePlaceholders($template, $request->fresh()->load('material'));

        $this->assertStringContainsString('9780123456789', $result);
        $this->assertStringNotContainsString('{isbn}', $result);
    }

    #[Test]
    public function isbn_token_replaced_from_material_isbn_when_isbn13_not_set(): void
    {
        $this->createPatron(1);
        $matId = $this->createMaterial('0123456789', null);
        $request = $this->createRequest(1, 'T', 'A', $matId);

        $template = 'ISBN: {isbn}';
        $result = $this->invokeReplacePlaceholders($template, $request->fresh()->load('material'));

        $this->assertStringContainsString('0123456789', $result);
        $this->assertStringNotContainsString('{isbn}', $result);
    }

    #[Test]
    public function isbn_token_replaced_from_field_value_when_no_material_isbn(): void
    {
        $this->createPatron(1);
        $request = $this->createRequest(1, 'T', 'A', null);
        $isbnFieldId = $this->createIsbnField();
        $this->setRequestFieldValue($request->id, $isbnFieldId, '0-123-45678-9');

        $template = 'ISBN: {isbn}';
        $result = $this->invokeReplacePlaceholders($template, $request->fresh()->load('fieldValues.field'));

        $this->assertStringContainsString('0-123-45678-9', $result);
        $this->assertStringNotContainsString('{isbn}', $result);
    }
}
