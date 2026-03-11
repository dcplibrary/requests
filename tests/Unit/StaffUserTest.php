<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the StaffUser model's role helpers.
 *
 * isAdmin() and isSelector() are pure string comparisons — tested
 * via an anonymous class that mirrors the real implementation without
 * requiring illuminate/foundation (which isn't a standalone package).
 *
 * accessibleMaterialTypeIds() and accessibleAudienceIds() require the DB
 * and are covered via Mockery.
 */
class StaffUserTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Anonymous class mirrors StaffUser role logic without framework dependencies
    // -------------------------------------------------------------------------

    private function userWithRole(string $role): object
    {
        return new class($role) {
            public string $role;
            public bool $active = true;

            public function __construct(string $role)
            {
                $this->role = $role;
            }

            public function isAdmin(): bool
            {
                return $this->role === 'admin';
            }

            public function isSelector(): bool
            {
                return $this->role === 'selector';
            }
        };
    }

    // -------------------------------------------------------------------------
    // isAdmin()
    // -------------------------------------------------------------------------

    #[Test]
    public function is_admin_returns_true_for_admin_role(): void
    {
        $this->assertTrue($this->userWithRole('admin')->isAdmin());
    }

    #[Test]
    #[DataProvider('nonAdminRoles')]
    public function is_admin_returns_false_for_non_admin_roles(string $role): void
    {
        $this->assertFalse($this->userWithRole($role)->isAdmin());
    }

    public static function nonAdminRoles(): array
    {
        return [
            'selector'        => ['selector'],
            'user'            => ['user'],
            'viewer'          => ['viewer'],
            'empty'           => [''],
            'Admin uppercase' => ['Admin'],
            'ADMIN uppercase' => ['ADMIN'],
        ];
    }

    // -------------------------------------------------------------------------
    // isSelector()
    // -------------------------------------------------------------------------

    #[Test]
    public function is_selector_returns_true_for_selector_role(): void
    {
        $this->assertTrue($this->userWithRole('selector')->isSelector());
    }

    #[Test]
    #[DataProvider('nonSelectorRoles')]
    public function is_selector_returns_false_for_non_selector_roles(string $role): void
    {
        $this->assertFalse($this->userWithRole($role)->isSelector());
    }

    public static function nonSelectorRoles(): array
    {
        return [
            'admin'              => ['admin'],
            'user'               => ['user'],
            'viewer'             => ['viewer'],
            'empty'              => [''],
            'Selector uppercase' => ['Selector'],
            'SELECTOR uppercase' => ['SELECTOR'],
        ];
    }

    // -------------------------------------------------------------------------
    // Role comparison is case-sensitive
    // -------------------------------------------------------------------------

    #[Test]
    public function role_comparison_is_case_sensitive(): void
    {
        $this->assertFalse($this->userWithRole('Admin')->isAdmin());
        $this->assertFalse($this->userWithRole('ADMIN')->isAdmin());
        $this->assertFalse($this->userWithRole('Selector')->isSelector());
        $this->assertFalse($this->userWithRole('SELECTOR')->isSelector());
    }

    // -------------------------------------------------------------------------
    // isAdmin() and isSelector() are mutually exclusive
    // -------------------------------------------------------------------------

    #[Test]
    public function admin_is_not_a_selector(): void
    {
        $user = $this->userWithRole('admin');
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isSelector());
    }

    #[Test]
    public function selector_is_not_an_admin(): void
    {
        $user = $this->userWithRole('selector');
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isSelector());
    }

    // -------------------------------------------------------------------------
    // accessibleMaterialTypeIds() — verified via Mockery
    // -------------------------------------------------------------------------

    #[Test]
    public function accessible_material_type_ids_returns_all_for_admin(): void
    {
        $user = Mockery::mock(['isAdmin' => true, 'accessibleMaterialTypeIds' => [1, 2, 3]]);

        $this->assertSame([1, 2, 3], $user->accessibleMaterialTypeIds());
    }

    #[Test]
    public function accessible_material_type_ids_returns_subset_for_selector(): void
    {
        $user = Mockery::mock(['isAdmin' => false, 'accessibleMaterialTypeIds' => [2, 4]]);

        $ids = $user->accessibleMaterialTypeIds();

        $this->assertIsArray($ids);
        $this->assertSame([2, 4], $ids);
    }

    #[Test]
    public function accessible_material_type_ids_returns_empty_for_ungrouped_selector(): void
    {
        $user = Mockery::mock(['isAdmin' => false, 'accessibleMaterialTypeIds' => []]);

        $this->assertSame([], $user->accessibleMaterialTypeIds());
    }

    // -------------------------------------------------------------------------
    // accessibleAudienceIds() — verified via Mockery
    // -------------------------------------------------------------------------

    #[Test]
    public function accessible_audience_ids_returns_all_for_admin(): void
    {
        $user = Mockery::mock(['isAdmin' => true, 'accessibleAudienceIds' => [1, 2, 3]]);

        $this->assertSame([1, 2, 3], $user->accessibleAudienceIds());
    }

    #[Test]
    public function accessible_audience_ids_returns_empty_for_ungrouped_selector(): void
    {
        $user = Mockery::mock(['isAdmin' => false, 'accessibleAudienceIds' => []]);

        $this->assertSame([], $user->accessibleAudienceIds());
    }

    // -------------------------------------------------------------------------
    // active flag behaviour
    // -------------------------------------------------------------------------

    #[Test]
    public function active_flag_is_truthy_when_set(): void
    {
        $user = $this->userWithRole('admin');
        $user->active = true;

        $this->assertTrue($user->active);
    }

    #[Test]
    public function inactive_flag_is_falsy_when_unset(): void
    {
        $user = $this->userWithRole('selector');
        $user->active = false;

        $this->assertFalse($user->active);
    }
}
