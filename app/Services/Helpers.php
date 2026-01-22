<?php

namespace App\Services;

use App\Constants\Translations;

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

    public static function extractStreetName($address)
    {
        // Remove all commas
        $address = str_replace(',', '', $address);

        // Match everything up to the first digit
        if (preg_match('/^[^\d]*/', $address, $matches)) {
            return trim($matches[0]);
        }

        return $address;
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
