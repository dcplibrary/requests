<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\PatronRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PatronRequest kind constants and kinds().
 */
class PatronRequestKindsTest extends TestCase
{
    #[Test]
    public function kinds_returns_sfp_and_ill(): void
    {
        $kinds = PatronRequest::kinds();

        $this->assertIsArray($kinds);
        $this->assertCount(2, $kinds);
        $this->assertContains(PatronRequest::KIND_SFP, $kinds);
        $this->assertContains(PatronRequest::KIND_ILL, $kinds);
    }

    #[Test]
    public function kind_constants_have_expected_values(): void
    {
        $this->assertSame('sfp', PatronRequest::KIND_SFP);
        $this->assertSame('ill', PatronRequest::KIND_ILL);
    }

    #[Test]
    public function kinds_array_equals_constant_values(): void
    {
        $this->assertSame([PatronRequest::KIND_SFP, PatronRequest::KIND_ILL], PatronRequest::kinds());
    }
}
