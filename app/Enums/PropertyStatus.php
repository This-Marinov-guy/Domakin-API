<?php

namespace App\Enums;

enum PropertyStatus: int
{
    case Pending = 1;
    case Rent = 2;
    case Taken = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Rent => 'rent',
            self::Taken => 'taken',
        };
    }
}