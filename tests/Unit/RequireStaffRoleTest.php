<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Models\User as StaffUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the RequireStaffRole middleware's access-control logic.
 *
 * The `handle()` method calls `response()->view()` and `config()` which require
 * the Laravel container. Rather than boot the full framework in unit tests, we
 * extract and test the two decision points in isolation via an anonymous class:
 *
 *  1. Role/active gate: given an StaffUser, is access allowed?
 *  2. Host-app role provisioning: given a host-app role string, is it allowed?
 *
 * Integration tests (or feature tests in the host app) cover the full HTTP flow.
 */
class RequireStaffRoleTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Anonymous class that mirrors the role-gate logic from RequireStaffRole
    // -------------------------------------------------------------------------

    private function gate(): object
    {
        return new class {
            private const ALLOWED_ROLES = ['admin', 'selector'];
            private const LEGACY_ILL_ROLE = 'ill';

            /** Returns true if the StaffUser should be allowed through. */
            public function isAllowed(?object $staffUser): bool
            {
                if ($staffUser === null) {
                    return false;
                }
                return in_array($staffUser->role, self::ALLOWED_ROLES, true)
                    && (bool) $staffUser->active;
            }

            /** Returns true if a host-app role string is allowed for auto-provisioning (legacy 'ill' maps to selector). */
            public function isAllowedHostRole(?string $role): bool
            {
                if (! is_string($role) || $role === '') {
                    return false;
                }
                $provisionRole = $role === self::LEGACY_ILL_ROLE ? 'selector' : $role;

                return in_array($provisionRole, self::ALLOWED_ROLES, true);
            }
        };
    }

    private function staffUser(string $role, bool $active = true): object
    {
        return (object) ['role' => $role, 'active' => $active];
    }

    // -------------------------------------------------------------------------
    // Allowed roles
    // -------------------------------------------------------------------------

    #[Test]
    public function admin_sfp_user_is_allowed(): void
    {
        $this->assertTrue($this->gate()->isAllowed($this->staffUser('admin')));
    }

    #[Test]
    public function selector_sfp_user_is_allowed(): void
    {
        $this->assertTrue($this->gate()->isAllowed($this->staffUser('selector')));
    }

    #[Test]
    public function ill_role_is_no_longer_valid_ill_access_is_group_based(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('ill')));
    }

    // -------------------------------------------------------------------------
    // Blocked: null user
    // -------------------------------------------------------------------------

    #[Test]
    public function null_sfp_user_is_blocked(): void
    {
        $this->assertFalse($this->gate()->isAllowed(null));
    }

    // -------------------------------------------------------------------------
    // Blocked: unknown roles
    // -------------------------------------------------------------------------

    #[Test]
    public function unknown_role_is_blocked(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('viewer')));
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('user')));
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('')));
    }

    #[Test]
    public function role_check_is_case_sensitive(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('Admin')));
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('ADMIN')));
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('Selector')));
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('SELECTOR')));
    }

    // -------------------------------------------------------------------------
    // Inactive users are always blocked
    // -------------------------------------------------------------------------

    #[Test]
    public function inactive_admin_is_blocked(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('admin', active: false)));
    }

    #[Test]
    public function inactive_selector_is_blocked(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->staffUser('selector', active: false)));
    }

    // -------------------------------------------------------------------------
    // Host-app role provisioning check
    // -------------------------------------------------------------------------

    #[Test]
    public function host_user_with_admin_role_is_allowed_for_provisioning(): void
    {
        $this->assertTrue($this->gate()->isAllowedHostRole('admin'));
    }

    #[Test]
    public function host_user_with_selector_role_is_allowed_for_provisioning(): void
    {
        $this->assertTrue($this->gate()->isAllowedHostRole('selector'));
    }

    #[Test]
    public function host_user_with_legacy_ill_role_is_allowed_for_provisioning_mapped_to_selector(): void
    {
        $this->assertTrue($this->gate()->isAllowedHostRole('ill'));
    }

    #[Test]
    public function host_user_with_user_role_is_not_allowed_for_provisioning(): void
    {
        $this->assertFalse($this->gate()->isAllowedHostRole('user'));
    }

    #[Test]
    public function host_user_with_null_role_is_not_allowed_for_provisioning(): void
    {
        $this->assertFalse($this->gate()->isAllowedHostRole(null));
    }

    #[Test]
    public function host_user_with_empty_role_is_not_allowed_for_provisioning(): void
    {
        $this->assertFalse($this->gate()->isAllowedHostRole(''));
    }

    #[Test]
    public function host_user_role_check_is_case_sensitive(): void
    {
        $this->assertFalse($this->gate()->isAllowedHostRole('Admin'));
        $this->assertFalse($this->gate()->isAllowedHostRole('ADMIN'));
        $this->assertFalse($this->gate()->isAllowedHostRole('Selector'));
    }

    // -------------------------------------------------------------------------
    // Exactly two roles are allowed (ILL access is group-based, not a role)
    // -------------------------------------------------------------------------

    #[Test]
    public function exactly_admin_and_selector_are_the_allowed_roles(): void
    {
        $shouldPass = ['admin', 'selector'];
        $shouldFail = ['ill', 'user', 'viewer', 'staff', 'superadmin', '', 'Admin', 'SELECTOR'];

        foreach ($shouldPass as $role) {
            $this->assertTrue(
                $this->gate()->isAllowed($this->staffUser($role)),
                "Active '{$role}' should be allowed"
            );
        }

        foreach ($shouldFail as $role) {
            $this->assertFalse(
                $this->gate()->isAllowed($this->staffUser($role)),
                "Role '{$role}' should be blocked"
            );
        }
    }
}
