<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\Patron;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\RequestStatus;
use Dcplibrary\Requests\Models\Setting;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PatronRequestLimitTest extends TestCase
{
    private static bool $booted = false;

    private static int $openStatusId;

    private static int $closedStatusId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootDatabase();
        Cache::flush();
    }

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

        $schema->create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $schema->create('request_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
        });

        $schema->create('patrons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('barcode')->unique();
            $table->string('name_first');
            $table->string('name_last');
            $table->string('phone');
            $table->timestamps();
        });

        $schema->create('requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('patron_id');
            $table->unsignedInteger('request_status_id');
            $table->string('request_kind')->nullable();
            $table->string('submitted_title');
            $table->timestamps();
        });

        self::$openStatusId = RequestStatus::create([
            'name'        => 'Pending',
            'slug'        => 'pending',
            'is_terminal' => false,
        ])->id;

        self::$closedStatusId = RequestStatus::create([
            'name'        => 'Done',
            'slug'        => 'done',
            'is_terminal' => true,
        ])->id;

        self::$booted = true;
    }

    private function seedSetting(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::flush();
    }

    private function createIllRequest(int $patronId, bool $terminal = false): void
    {
        PatronRequest::create([
            'patron_id'          => $patronId,
            'request_status_id'  => $terminal ? self::$closedStatusId : self::$openStatusId,
            'request_kind'       => PatronRequest::KIND_ILL,
            'submitted_title'    => 'Test Title',
        ]);
    }

    #[Test]
    public function concurrent_ill_limit_counts_only_open_requests(): void
    {
        $this->seedSetting('ill_limit_count', '2');
        $this->seedSetting('ill_limit_window_type', Patron::LIMIT_TYPE_CONCURRENT);

        $patron = Patron::create([
            'barcode'    => '12345',
            'name_first' => 'A',
            'name_last'  => 'B',
            'phone'      => '5555555555',
        ]);

        $this->createIllRequest($patron->id, false);
        $this->assertFalse($patron->fresh()->hasReachedLimit(PatronRequest::KIND_ILL));

        $this->createIllRequest($patron->id, false);
        $this->assertTrue($patron->fresh()->hasReachedLimit(PatronRequest::KIND_ILL));

        $this->createIllRequest($patron->id, true);
        $this->assertTrue($patron->fresh()->hasReachedLimit(PatronRequest::KIND_ILL));

        PatronRequest::where('patron_id', $patron->id)
            ->where('request_status_id', self::$openStatusId)
            ->limit(1)
            ->update(['request_status_id' => self::$closedStatusId]);

        $this->assertFalse($patron->fresh()->hasReachedLimit(PatronRequest::KIND_ILL));
        $this->assertNull($patron->fresh()->nextAvailableDate(PatronRequest::KIND_ILL));
    }

    #[Test]
    public function rolling_sfp_limit_uses_date_window(): void
    {
        $this->seedSetting('sfp_limit_count', '1');
        $this->seedSetting('sfp_limit_window_type', 'rolling');
        $this->seedSetting('sfp_limit_window_days', '30');

        $patron = Patron::create([
            'barcode'    => '99999',
            'name_first' => 'C',
            'name_last'  => 'D',
            'phone'      => '5555555556',
        ]);

        $old = PatronRequest::create([
            'patron_id'         => $patron->id,
            'request_status_id' => self::$closedStatusId,
            'request_kind'      => PatronRequest::KIND_SFP,
            'submitted_title'   => 'Old',
        ]);
        $old->forceFill([
            'created_at' => Carbon::now()->subDays(40),
            'updated_at' => Carbon::now()->subDays(40),
        ])->saveQuietly();

        $this->assertFalse($patron->fresh()->hasReachedLimit(PatronRequest::KIND_SFP));

        PatronRequest::create([
            'patron_id'         => $patron->id,
            'request_status_id' => self::$openStatusId,
            'request_kind'      => PatronRequest::KIND_SFP,
            'submitted_title'   => 'Recent',
        ]);

        $this->assertTrue($patron->fresh()->hasReachedLimit(PatronRequest::KIND_SFP));
        $this->assertSame(1, $patron->fresh()->recentRequestCount(PatronRequest::KIND_SFP));
    }
}
