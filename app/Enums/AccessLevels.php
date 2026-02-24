<?php

namespace App\Enums;

enum AccessLevels
{
    case LEVEL_1;

    /** @return Roles[] */
    public function roles(): array
    {
        return match($this) {
            AccessLevels::LEVEL_1 => [Roles::ADMIN, Roles::EDITOR, Roles::AGENT],
        };
    }
}
