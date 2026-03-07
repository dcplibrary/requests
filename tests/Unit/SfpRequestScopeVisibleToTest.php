<?php

namespace Dcplibrary\Sfp\Tests\Unit;

use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Models\User as SfpUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SfpRequest::scopeVisibleTo().
 *
 * These tests call the real scope method on the model but use a mocked Builder
 * so no database or full Laravel application is required.
 */
class SfpRequestScopeVisibleToTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function model(): SfpRequest
    {
        return new SfpRequest();
    }

    private function mockBuilder(): MockInterface
    {
        return Mockery::mock(Builder::class);
    }

    private function sfpUser(string $role, int $id = 1): MockInterface
    {
        $user = Mockery::mock(SfpUser::class);
        $user->shouldReceive('getKey')->andReturn($id)->byDefault();
        $user->shouldReceive('isAdmin')->andReturn($role === 'admin');
        return $user;
    }

    // -------------------------------------------------------------------------
    // null user → no rows
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_no_rows_for_null_user(): void
    {
        $builder = $this->mockBuilder();
        $builder->shouldReceive('whereRaw')->with('1 = 0')->once()->andReturnSelf();

        $result = $this->model()->scopeVisibleTo($builder, null);

        $this->assertSame($builder, $result);
    }

    // -------------------------------------------------------------------------
    // Admin → no filter
    // -------------------------------------------------------------------------

    #[Test]
    public function it_applies_no_filter_for_admin(): void
    {
        $user    = $this->sfpUser('admin');
        $builder = $this->mockBuilder();

        $builder->shouldNotReceive('whereRaw');
        $builder->shouldNotReceive('whereExists');

        $result = $this->model()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }

    // -------------------------------------------------------------------------
    // Selector → filters by selector group pairing (EXISTS subquery)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_applies_group_scoping_for_selector_via_where_exists(): void
    {
        $user    = $this->sfpUser('selector', id: 123);
        $builder = $this->mockBuilder();

        $builder->shouldReceive('whereExists')->once()->andReturnSelf();
        $builder->shouldNotReceive('where');
        $builder->shouldNotReceive('whereIn');

        $result = $this->model()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function selector_with_no_groups_still_uses_where_exists_filter(): void
    {
        // Without a DB, we can't validate the subquery results here — that is covered
        // by an integration test. This unit test ensures the scope doesn't "fall back"
        // to showing everything for selectors.
        $user    = $this->sfpUser('selector', id: 999);
        $builder = $this->mockBuilder();

        $builder->shouldReceive('whereExists')->once()->andReturnSelf();
        $builder->shouldNotReceive('whereRaw');

        $result = $this->model()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }
}
