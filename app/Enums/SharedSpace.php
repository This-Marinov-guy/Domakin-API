<?php

namespace App\Enums;

/**
 * Shared space: index = value stored in DB, label for display (matches frontend SHARED_SPACE_LIST).
 */
final class SharedSpace
{
    public const LABELS = [
        0 => 'Balcony',
        1 => 'Kitchen',
        2 => 'Bathroom',
        3 => 'Toilet',
        4 => 'Storage space',
        5 => 'Living room',
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
