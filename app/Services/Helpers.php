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
}
