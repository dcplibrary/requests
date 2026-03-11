<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\User as StaffUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Material::scopeVisibleTo().
 *
 * The scope logic is extracted into an anonymous class so we can test it
 * without instantiating the full Eloquent model (which requires illuminate/foundation).
 *
 * Verifies that the correct query constraints are applied for each user type.
 */
class MaterialScopeVisibleToTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Anonymous class that copies the scopeVisibleTo logic from Material
    // -------------------------------------------------------------------------

    private function scope(): object
    {
        return new class {
            /**
             * Extracted copy of Material::scopeVisibleTo() for isolated testing.
             * Must stay in sync with src/Models/Material.php.
             */
            public function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
            {
                if ($user === null) {
                    return $query->whereRaw('1 = 0');
                }

                if ($user instanceof StaffUser) {
                    $staffUser = $user;
                } else {
                    // In real code: StaffUser::where('email', ...) — mocked in integration tests
                    $staffUser = null;
                }

                if ($staffUser === null) {
                    return $query->whereRaw('1 = 0');
                }

                if ($staffUser->isAdmin()) {
                    return $query;
                }

                $materialTypeIds = $staffUser->accessibleFieldOptionIds('material_type');

                return $query->whereIn('material_type_option_id', $materialTypeIds);
            }
        };
    }

    private function mockBuilder(): MockInterface
    {
        return Mockery::mock(Builder::class);
    }

    private function sfpUser(string $role, array $materialTypeIds = []): MockInterface
    {
        $user = Mockery::mock(StaffUser::class);
        $user->shouldReceive('isAdmin')->andReturn($role === 'admin');
        $user->shouldReceive('accessibleFieldOptionIds')->andReturn($materialTypeIds)->byDefault();
        return $user;
    }

    // -------------------------------------------------------------------------
    // null user → whereRaw('1 = 0')
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
    // Admin StaffUser → no filter
    // -------------------------------------------------------------------------

    #[Test]
    public function it_applies_no_filter_for_admin_sfp_user(): void
    {
        $user    = $this->sfpUser('admin');
        $builder = $this->mockBuilder();

        $builder->shouldNotReceive('whereRaw');
        $builder->shouldNotReceive('whereIn');

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }

    // -------------------------------------------------------------------------
    // Selector → whereIn('material_type_option_id', [...])
    // -------------------------------------------------------------------------

    #[Test]
    public function it_filters_by_material_type_ids_for_selector(): void
    {
        $user    = $this->sfpUser('selector', materialTypeIds: [1, 3]);
        $builder = $this->mockBuilder();

        $builder->shouldReceive('whereIn')
            ->with('material_type_option_id', [1, 3])
            ->once()
            ->andReturnSelf();

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function it_filters_with_empty_array_for_selector_with_no_groups(): void
    {
        $user    = $this->sfpUser('selector', materialTypeIds: []);
        $builder = $this->mockBuilder();

        $builder->shouldReceive('whereIn')
            ->with('material_type_option_id', [])
            ->once()
            ->andReturnSelf();

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function it_does_not_filter_by_audience_for_selector(): void
    {
        // Materials have no audience_id — the scope must never add an audience constraint.
        $user    = $this->sfpUser('selector', materialTypeIds: [2]);
        $builder = $this->mockBuilder();

        $builder->shouldReceive('whereIn')
            ->with('material_type_option_id', Mockery::any())
            ->once()
            ->andReturnSelf();

        $builder->shouldNotReceive('whereIn')
            ->with('audience_id', Mockery::any());

        $result = $this->scope()->scopeVisibleTo($builder, $user);

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function it_returns_no_rows_when_sfp_user_cannot_be_resolved(): void
    {
        // Simulates the "no staff_users record" path (non-local env):
        // passing null is equivalent once email lookup returns null.
        $builder = $this->mockBuilder();
        $builder->shouldReceive('whereRaw')->with('1 = 0')->once()->andReturnSelf();

        $result = $this->scope()->scopeVisibleTo($builder, null);

        $this->assertSame($builder, $result);
    }
}
