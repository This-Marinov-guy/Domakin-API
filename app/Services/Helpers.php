<?php

namespace App\Services;

use App\Constants\Translations;
use Illuminate\Support\Carbon;

class Helpers
{
    public static function getDefaultLocalesJSON()
    {
        $languages = Translations::WEB_SUPPORTED_LOCALES;
        return json_encode(array_combine($languages, array_fill(0, count($languages), "")), JSON_PRETTY_PRINT);
    }

    public static function getDefaultLocalesObject()
    {
        $languages = Translations::WEB_SUPPORTED_LOCALES;
        return array_combine($languages, array_fill(0, count($languages), ""));
    }

    public static function camelToSnakeObject(array $obj): array
    {
        $result = [];

        foreach ((array) $obj as $key => $value) {
            $snakeKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            $result[$snakeKey] = $value;
        }

        return $result;
    }

    public static function snakeToCamelObject(array $obj): array
    {
        $result = [];

        foreach ((array) $obj as $key => $value) {
            $camelKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            $result[$camelKey] = $value;
        }

        return $result;
    }


    public static function decodeJsonKeys(array $items, array $keys): array
    {
        foreach ($items as &$item) {
            foreach ($keys as $keyPath) {
                $parts = explode('.', $keyPath);
                $current = &$item;

                // Navigate to the parent of the target value
                $pathExists = true;
                for ($i = 0; $i < count($parts) - 1; $i++) {
                    if (!isset($current[$parts[$i]])) {
                        $pathExists = false;
                        break;
                    }
                    $current = &$current[$parts[$i]];
                }

                if ($pathExists) {
                    $lastKey = end($parts);
                    if (isset($current[$lastKey]) && is_string($current[$lastKey])) {
                        $decoded = json_decode($current[$lastKey], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $current[$lastKey] = $decoded;
                        }
                    }
                }
            }
        }
        return $items;
    }

    public static function splitStringKeys(array $items, array $keys): array
    {
        foreach ($items as &$item) {
            foreach ($keys as $keyPath) {
                $parts = explode('.', $keyPath);
                $current = &$item;

                // Navigate to the parent of the target value
                $pathExists = true;
                for ($i = 0; $i < count($parts) - 1; $i++) {
                    if (!isset($current[$parts[$i]])) {
                        $pathExists = false;
                        break;
                    }
                    $current = &$current[$parts[$i]];
                }

                if ($pathExists) {
                    $lastKey = end($parts);
                    if (isset($current[$lastKey]) && is_string($current[$lastKey])) {
                        $current[$lastKey] = array_map('trim', explode(', ', $current[$lastKey]));
                    }
                }
            }
        }
        return $items;
    }

    public static function extractStreetName(string $address): string
    {
        // Replace commas with spaces
        $address = str_replace(',', ' ', $address);

        // Remove any token that contains a digit (12, 12A, A12, 221B, 4/2, A-12, etc.)
        $address = preg_replace('/\b\S*\d\S*\b/u', ' ', $address);

        // Remove 1–2 character words (A, B, II, 3? already removed above)
        $address = preg_replace('/\b\p{L}{1,2}\b/u', ' ', $address);

        // Collapse whitespace
        $address = preg_replace('/\s+/u', ' ', $address);

        return trim($address);
    }

    public static function getPreferredLanguage($default = 'en')
    {
        $langHeader = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (preg_match('/^([a-z]{2})/i', $langHeader, $matches)) {
            return strtolower($matches[1]);
        }
        return $default;
    }

    public static function getTranslatedValue($value, $locale = 'en', $escapeEmptyValues = true, $fallback = '')
    {
        // If the value is null, return fallback immediately
        if (empty($value)) {
            return $fallback;
        }

        // If the value is not an array, return it as-is
        if (!is_array($value)) {
            return $value;
        }

        $translated = $value[$locale] ?? null;

        if ($escapeEmptyValues) {
            if (!empty($translated)) {
                return $translated;
            }

            if (!empty($value['en'])) {
                return $value['en'];
            }

            return $fallback;
        }

        return $translated ?? $value['en'] ?? $fallback;
    }

    /**
     * Normalize any date input to a consistent DB format (Y-m-d).
     *
     * Accepts: Carbon instances, PHP DateTime objects, ISO strings (Y-m-d),
     * JS ISO strings (Y-m-dTH:i:s.vZ), already-formatted strings (d-m-Y),
     * or any other value parseable by Carbon::parse().
     *
     * Returns null for: null, empty string, 'null', arrays, objects, or values that cannot be parsed.
     */
    public static function formatDate(mixed $date, string $format = 'Y-m-d'): ?string
    {
        if ($date === null || $date === '' || $date === 'null') {
            return null;
        }

        if (is_array($date) || (is_object($date) && !($date instanceof \DateTimeInterface))) {
            return null;
        }

        try {
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->format($format);
            }

            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sanitize and format slug
     *
     * @param string $text
     * @return string
     */
    public static function sanitizeSlug(string $text): string
    {
        // Convert to lowercase
        $slug = strtolower($text);

        // Replace spaces and underscores with hyphens
        $slug = preg_replace('/[\s_]+/', '-', $slug);

        // Remove all characters that are not alphanumeric or hyphens
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Remove leading and trailing hyphens
        $slug = trim($slug, '-');

        // Truncate to 90 characters
        if (strlen($slug) > 90) {
            $slug = substr($slug, 0, 90);
            // Remove trailing hyphen if truncation created one
            $slug = rtrim($slug, '-');
        }

        // Ensure slug is not empty
        if (empty($slug)) {
            $slug = 'property';
        }

        return $slug;
    }
}
