<?php

namespace App\Helpers;

class Common
{
    public static function getPreferredLanguage($default = 'en')
    {
        // Fallback to Accept-Language header
        $langHeader = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (preg_match('/^([a-z]{2})/i', $langHeader, $matches)) {
            return strtolower($matches[1]);
        }

        // Check for query parameter first
        if (!empty($_GET['language']) && preg_match('/^[a-z]{2}$/i', $_GET['language'])) {
            return strtolower($_GET['language']);
        }

        return $default;
    }
}
