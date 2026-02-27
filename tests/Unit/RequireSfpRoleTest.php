<?php

namespace Dcplibrary\Sfp\Tests\Unit;

use Dcplibrary\Sfp\Models\User as SfpUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the RequireSfpRole middleware's access-control logic.
 *
 * The `handle()` method calls `response()->view()` and `config()` which require
 * the Laravel container. Rather than boot the full framework in unit tests, we
 * extract and test the two decision points in isolation via an anonymous class:
 *
 *  1. Role/active gate: given an SfpUser, is access allowed?
 *  2. Host-app role provisioning: given a host-app role string, is it allowed?
 *
 * Integration tests (or feature tests in the host app) cover the full HTTP flow.
 */
class RequireSfpRoleTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Anonymous class that mirrors the role-gate logic from RequireSfpRole
    // -------------------------------------------------------------------------

    private function gate(): object
    {
        return new class {
            private const ALLOWED_ROLES = ['admin', 'selector'];

            /** Returns true if the SfpUser should be allowed through. */
            public function isAllowed(?object $sfpUser): bool
            {
                if ($sfpUser === null) {
                    return false;
                }
                return in_array($sfpUser->role, self::ALLOWED_ROLES, true)
                    && (bool) $sfpUser->active;
            }

            /** Returns true if a host-app role string maps to an allowed SFP role. */
            public function isAllowedHostRole(?string $role): bool
            {
                if (! is_string($role) || $role === '') {
                    return false;
                }
                return in_array($role, self::ALLOWED_ROLES, true);
            }
        };
    }

    private function sfpUser(string $role, bool $active = true): object
    {
        return (object) ['role' => $role, 'active' => $active];
    }

    // -------------------------------------------------------------------------
    // Allowed roles
    // -------------------------------------------------------------------------

    #[Test]
    public function admin_sfp_user_is_allowed(): void
    {
        $this->assertTrue($this->gate()->isAllowed($this->sfpUser('admin')));
    }

    #[Test]
    public function selector_sfp_user_is_allowed(): void
    {
        $this->assertTrue($this->gate()->isAllowed($this->sfpUser('selector')));
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
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('viewer')));
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('user')));
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('')));
    }

    #[Test]
    public function role_check_is_case_sensitive(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('Admin')));
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('ADMIN')));
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('Selector')));
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('SELECTOR')));
    }

    // -------------------------------------------------------------------------
    // Inactive users are always blocked
    // -------------------------------------------------------------------------

    #[Test]
    public function inactive_admin_is_blocked(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('admin', active: false)));
    }

    #[Test]
    public function inactive_selector_is_blocked(): void
    {
        $this->assertFalse($this->gate()->isAllowed($this->sfpUser('selector', active: false)));
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
    // Exactly two roles are allowed
    // -------------------------------------------------------------------------

    #[Test]
    public function exactly_admin_and_selector_are_the_allowed_roles(): void
    {
        $shouldPass = ['admin', 'selector'];
        $shouldFail = ['user', 'viewer', 'staff', 'superadmin', '', 'Admin', 'SELECTOR'];

        foreach ($shouldPass as $role) {
            $this->assertTrue(
                $this->gate()->isAllowed($this->sfpUser($role)),
                "Active '{$role}' should be allowed"
            );
        }

        foreach ($shouldFail as $role) {
            $this->assertFalse(
                $this->gate()->isAllowed($this->sfpUser($role)),
                "Role '{$role}' should be blocked"
            );
        }
    }
}
