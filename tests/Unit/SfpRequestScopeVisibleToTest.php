<?php

namespace Dcplibrary\Sfp\Tests\Unit;

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
 * The scope logic is extracted into an anonymous class so we can test it
 * without instantiating the full Eloquent model (which requires illuminate/foundation).
 *
 * Unlike Material scope, the request scope filters on BOTH material_type_id
 * AND audience_id for selectors.
 */
class SfpRequestScopeVisibleToTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Anonymous class that copies the scopeVisibleTo logic from SfpRequest
    // -------------------------------------------------------------------------

    private function scope(): object
    {
        return new class {
            /**
             * Extracted copy of SfpRequest::scopeVisibleTo() for isolated testing.
             * Must stay in sync with src/Models/SfpRequest.php.
             */
            public function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
            {
                if ($user === null) {
                    return $query->whereRaw('1 = 0');
                }

                if ($user instanceof SfpUser) {
                    $sfpUser = $user;
                } else {
                    // In real code: SfpUser::where('email', ...) — covered in integration tests
                    $sfpUser = null;
                }

                if ($sfpUser === null) {
                    return $query->whereRaw('1 = 0');
                }

                if ($sfpUser->isAdmin()) {
                    return $query;
                }

                $materialTypeIds = $sfpUser->accessibleMaterialTypeIds();
                $audienceIds     = $sfpUser->accessibleAudienceIds();

                return $query->where(function ($q) use ($materialTypeIds, $audienceIds) {
                    $q->whereIn('material_type_id', $materialTypeIds)
                      ->whereIn('audience_id', $audienceIds);
                });
            }
        };
    }

    private function mockBuilder(): MockInterface
    {
        return Mockery::mock(Builder::class);
    }

    private function sfpUser(string $role, array $materialTypeIds = [], array $audienceIds = []): MockInterface
    {
        $user = Mockery::mock(SfpUser::class);
        $user->shouldReceive('isAdmin')->andReturn($role === 'admin');
        $user->shouldReceive('accessibleMaterialTypeIds')->andReturn($materialTypeIds)->byDefault();
        $user->shouldReceive('accessibleAudienceIds')->andReturn($audienceIds)->byDefault();
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

        $result = $this->scope()->scopeVisibleTo($builder, null);

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
        $builder->shouldNotReceive('whereIn');
        $builder->shouldNotReceive('where');

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }

    // -------------------------------------------------------------------------
    // Selector → filters on BOTH material_type_id AND audience_id
    // -------------------------------------------------------------------------

    #[Test]
    public function it_filters_by_both_material_type_and_audience_for_selector(): void
    {
        $user    = $this->sfpUser('selector', materialTypeIds: [1, 2], audienceIds: [10, 20]);
        $builder = $this->mockBuilder();

        $capturedClosure = null;
        $builder->shouldReceive('where')
            ->once()
            ->withArgs(function ($arg) use (&$capturedClosure) {
                if (is_callable($arg)) { $capturedClosure = $arg; return true; }
                return false;
            })
            ->andReturnSelf();

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
        $this->assertNotNull($capturedClosure, 'where() closure should have been captured');

        $innerBuilder = $this->mockBuilder();
        $innerBuilder->shouldReceive('whereIn')->with('material_type_id', [1, 2])->once()->andReturnSelf();
        $innerBuilder->shouldReceive('whereIn')->with('audience_id', [10, 20])->once()->andReturnSelf();

        $capturedClosure($innerBuilder);
    }

    #[Test]
    public function selector_with_no_groups_gets_empty_id_arrays(): void
    {
        $user    = $this->sfpUser('selector', materialTypeIds: [], audienceIds: []);
        $builder = $this->mockBuilder();

        $capturedClosure = null;
        $builder->shouldReceive('where')
            ->once()
            ->withArgs(function ($arg) use (&$capturedClosure) {
                if (is_callable($arg)) { $capturedClosure = $arg; return true; }
                return false;
            })
            ->andReturnSelf();

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
        $this->assertNotNull($capturedClosure, 'where() closure should have been captured');

        $innerBuilder = $this->mockBuilder();
        $innerBuilder->shouldReceive('whereIn')->with('material_type_id', [])->once()->andReturnSelf();
        $innerBuilder->shouldReceive('whereIn')->with('audience_id', [])->once()->andReturnSelf();

        $capturedClosure($innerBuilder);
    }

    // -------------------------------------------------------------------------
    // Key difference from Material scope: audience IS filtered
    // -------------------------------------------------------------------------

    #[Test]
    public function request_scope_filters_audience_unlike_material_scope(): void
    {
        // Documents the intentional difference: requests have audience_id, materials do not.
        $user    = $this->sfpUser('selector', materialTypeIds: [1], audienceIds: [2]);
        $builder = $this->mockBuilder();

        $capturedClosure = null;
        $builder->shouldReceive('where')
            ->once()
            ->withArgs(function ($arg) use (&$capturedClosure) {
                if (is_callable($arg)) { $capturedClosure = $arg; return true; }
                return false;
            })
            ->andReturnSelf();

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
        $this->assertNotNull($capturedClosure, 'where() closure should have been captured');

        $innerBuilder = $this->mockBuilder();
        $innerBuilder->shouldReceive('whereIn')->with('material_type_id', [1])->once()->andReturnSelf();
        // audience_id MUST be filtered — this is the key difference from Material
        $innerBuilder->shouldReceive('whereIn')->with('audience_id', [2])->once()->andReturnSelf();

        $capturedClosure($innerBuilder);
    }

    #[Test]
    public function request_scope_uses_where_closure_not_direct_whereIn(): void
    {
        // The selector filter must be wrapped in a where() closure so both conditions
        // are grouped together as AND — not as separate top-level constraints.
        $user    = $this->sfpUser('selector', materialTypeIds: [5], audienceIds: [3]);
        $builder = $this->mockBuilder();

        // where() must be called (closure grouping)
        $builder->shouldReceive('where')->once()->andReturnSelf();
        // whereIn should NOT be called directly on the outer builder
        $builder->shouldNotReceive('whereIn');

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }
}
