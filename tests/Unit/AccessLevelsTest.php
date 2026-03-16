<?php

namespace Tests\Unit;

use App\Enums\AccessLevels;
use App\Enums\Roles;
use PHPUnit\Framework\TestCase;

class AccessLevelsTest extends TestCase
{
    public function test_level_1_returns_admin_editor_agent(): void
    {
        $roles = AccessLevels::LEVEL_1->roles();
        $this->assertContains(Roles::ADMIN, $roles);
        $this->assertContains(Roles::EDITOR, $roles);
        $this->assertContains(Roles::AGENT, $roles);
    }

    public function test_level_1_does_not_include_user(): void
    {
        $roles = AccessLevels::LEVEL_1->roles();
        $this->assertNotContains(Roles::USER, $roles);
    }

    public function test_roles_returns_array(): void
    {
        $this->assertIsArray(AccessLevels::LEVEL_1->roles());
    }

    public function test_roles_are_not_empty(): void
    {
        $this->assertNotEmpty(AccessLevels::LEVEL_1->roles());
    }
}
