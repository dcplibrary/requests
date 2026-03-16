<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\RequestStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that RequestStatus model includes applies_to_sfp and applies_to_ill
 * in fillable and casts.
 */
class RequestStatusModelKindFlagsTest extends TestCase
{
    #[Test]
    public function request_status_fillable_includes_applies_to_sfp_and_applies_to_ill(): void
    {
        $model = new RequestStatus();

        $this->assertContains('applies_to_sfp', $model->getFillable());
        $this->assertContains('applies_to_ill', $model->getFillable());
    }

    #[Test]
    public function request_status_casts_applies_to_sfp_and_applies_to_ill_as_boolean(): void
    {
        $model = new RequestStatus();

        $casts = $model->getCasts();
        $this->assertArrayHasKey('applies_to_sfp', $casts);
        $this->assertSame('boolean', $casts['applies_to_sfp']);
        $this->assertArrayHasKey('applies_to_ill', $casts);
        $this->assertSame('boolean', $casts['applies_to_ill']);
    }
}
