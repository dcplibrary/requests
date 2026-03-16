<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\RequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RequestStatus::scopeForKind().
 */
class RequestStatusScopeForKindTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockBuilder(): MockInterface
    {
        return Mockery::mock(Builder::class)->shouldIgnoreMissing();
    }

    #[Test]
    public function scope_for_kind_filters_by_applies_to_sfp_when_kind_is_sfp(): void
    {
        $builder = $this->mockBuilder();
        $builder->shouldReceive('where')->once()->with('applies_to_sfp', true)->andReturnSelf();

        $result = (new RequestStatus())->scopeForKind($builder, 'sfp');

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function scope_for_kind_filters_by_applies_to_ill_when_kind_is_ill(): void
    {
        $builder = $this->mockBuilder();
        $builder->shouldReceive('where')->once()->with('applies_to_ill', true)->andReturnSelf();

        $result = (new RequestStatus())->scopeForKind($builder, 'ill');

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function scope_for_kind_does_not_add_where_when_kind_is_null(): void
    {
        $builder = $this->mockBuilder();
        $builder->shouldNotReceive('where');

        $result = (new RequestStatus())->scopeForKind($builder, null);

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function scope_for_kind_does_not_add_where_when_kind_is_unknown(): void
    {
        $builder = $this->mockBuilder();
        $builder->shouldNotReceive('where');

        $result = (new RequestStatus())->scopeForKind($builder, 'other');

        $this->assertSame($builder, $result);
    }
}
