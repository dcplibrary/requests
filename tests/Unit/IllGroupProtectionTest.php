<?php

namespace Dcplibrary\Sfp\Tests\Unit;

use Dcplibrary\Sfp\Models\Setting;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IllGroupProtectionTest extends TestCase
{
    #[Test]
    public function ill_selector_group_id_can_be_fetched_without_db_by_cached_attrs(): void
    {
        Cache::put('setting:ill_selector_group_id', ['type' => 'integer', 'value' => '123'], 3600);

        $this->assertSame(123, Setting::get('ill_selector_group_id', 0));
    }
}

