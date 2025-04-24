<?php

namespace App\Services;

use App\Constants\Translations;

class Helpers
{
    public static function getDefaultLocalesJSON()
    {
        $languages = Translations::SUPPORTED_LOCALES;
        return json_encode(array_combine($languages, array_fill(0, count($languages), "")), JSON_PRETTY_PRINT);
    }

    public static function getDefaultLocalesObject()
    {
        $languages = Translations::SUPPORTED_LOCALES;
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
}
