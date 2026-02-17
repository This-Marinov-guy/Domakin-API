<?php

namespace App\Enums;

/**
 * Property type: DB value 1,2,3,4 → label (matches frontend PROPERTY_TYPE_LABELS).
 */
enum PropertyType: int
{
    case RoomInSharedProperty = 1;
    case Studio = 2;
    case Apartment = 3;
    case House = 4;

    public function label(): string
    {
        return match ($this) {
            self::RoomInSharedProperty => 'Room in a shared property',
            self::Studio => 'Studio',
            self::Apartment => 'Apartment',
            self::House => 'House',
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
