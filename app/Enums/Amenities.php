<?php

namespace App\Enums;

/**
 * Amenities: index = value stored in DB, label for display (matches frontend AMENITIES_LIST).
 */
final class Amenities
{
    public const LABELS = [
        0 => 'Air Conditioning',
        1 => 'Washing Machine',
        2 => 'Dishwasher',
        3 => 'Microwave',
        4 => 'Stove',
        5 => 'Oven',
        6 => 'Bike Space',
        7 => 'Garage',
        8 => 'Parking',
        9 => 'Storage Space',
        10 => 'Garden',
        11 => 'Disabled Access',
        12 => 'Wi-fi',
        13 => 'BBQ',
    ];

    public static function getLabel(int $id): string
    {
        return self::LABELS[$id] ?? "#{$id}";
    }

    /**
     * Parse stored value (e.g. "0,2,5" or "1") and return comma-separated labels.
     */
    public static function getLabelsForValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $ids = array_map('intval', array_filter(preg_split('/[\s,]+/', $value)));
        $labels = array_map(fn (int $id) => self::getLabel($id), $ids);

        return implode(', ', $labels);
    }
}
