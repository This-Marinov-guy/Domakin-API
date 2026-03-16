<?php

namespace Tests\Unit;

use App\Enums\Roles;
use PHPUnit\Framework\TestCase;

class RolesTest extends TestCase
{
    public function test_admin_role_value(): void
    {
        $this->assertSame('admin', Roles::ADMIN->value);
    }

    public function test_user_role_value(): void
    {
        $this->assertSame('user', Roles::USER->value);
    }

    public function test_editor_role_value(): void
    {
        $this->assertSame('editor', Roles::EDITOR->value);
    }

    public function test_agent_role_value(): void
    {
        $this->assertSame('agent', Roles::AGENT->value);
    }

    public function test_can_instantiate_from_value(): void
    {
        $role = Roles::from('admin');
        $this->assertSame(Roles::ADMIN, $role);
    }

    public function test_try_from_returns_null_for_invalid(): void
    {
        $this->assertNull(Roles::tryFrom('superadmin'));
    }

    public function test_all_roles_have_string_values(): void
    {
        foreach (Roles::cases() as $role) {
            $this->assertIsString($role->value);
            $this->assertNotEmpty($role->value);
        }
    }
}
