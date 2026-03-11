<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\User as StaffUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PatronRequest::scopeVisibleTo().
 *
 * These tests call the real scope method on the model but use a mocked Builder
 * so no database or full Laravel application is required.
 */
class PatronRequestScopeVisibleToTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function model(): PatronRequest
    {
        return new PatronRequest();
    }

    private function mockBuilder(): MockInterface
    {
        return Mockery::mock(Builder::class)->shouldIgnoreMissing();
    }

    private function sfpUser(string $role, int $id = 1): MockInterface
    {
        $user = Mockery::mock(StaffUser::class);
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
    public function it_executes_for_selector_without_bootstrapped_facades(): void
    {
        // Selector path now queries Field model via Eloquent, which requires a
        // real DB connection. Covered fully by Integration\RequestPermissionsTest.
        $this->markTestSkipped('Selector scoping requires DB — see RequestPermissionsTest.');
    }

    #[Test]
    public function it_executes_for_selector_with_no_groups_without_throwing(): void
    {
        // See above — covered by Integration\RequestPermissionsTest.
        $this->markTestSkipped('Selector scoping requires DB — see RequestPermissionsTest.');
    }

    // Behavior details are covered by SQLite integration tests; these unit tests
    // primarily ensure the scope is callable without a full Laravel app boot.
}
