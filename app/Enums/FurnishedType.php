<?php

namespace App\Enums;

/**
 * Furnished type: DB value 1,2,3 → label (matches frontend FURNISHED_TYPE_LABELS).
 */
enum FurnishedType: int
{
    case FullyFurnished = 1;
    case SemiFurnished = 2;
    case None = 3;

    public function label(): string
    {
        return match ($this) {
            self::FullyFurnished => 'Fully furnished',
            self::SemiFurnished => 'Semi-furnished',
            self::None => 'None',
        };
    }

    public static function tryLabel(?int $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $case = self::tryFrom($value);

        return $case?->label();
    }
}
