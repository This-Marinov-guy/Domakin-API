<?php

namespace App\Services;

use App\Constants\Translations;

class Helpers
{
    public function getDefaultLocalesJSON()
    {
        $languages = Translations::SUPPORTED_LOCALES;
        return json_encode(array_combine($languages, array_fill(0, count($languages), "")), JSON_PRETTY_PRINT);
    }

    public function getDefaultLocalesObject()
    {
        $languages = Translations::SUPPORTED_LOCALES;
        return array_combine($languages, array_fill(0, count($languages), ""));
    }
}
