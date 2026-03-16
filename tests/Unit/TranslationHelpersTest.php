<?php

namespace Tests\Unit;

use App\Services\Helpers;
use App\Constants\Translations;
use PHPUnit\Framework\TestCase;

class TranslationHelpersTest extends TestCase
{
    public function test_default_locales_json_contains_all_supported_locales(): void
    {
        $json = Helpers::getDefaultLocalesJSON();
        $decoded = json_decode($json, true);

        foreach (Translations::WEB_SUPPORTED_LOCALES as $locale) {
            $this->assertArrayHasKey($locale, $decoded);
        }
    }

    public function test_default_locales_object_keys_match_supported_locales(): void
    {
        $obj = Helpers::getDefaultLocalesObject();
        $this->assertSame(Translations::WEB_SUPPORTED_LOCALES, array_keys($obj));
    }

    public function test_default_locales_values_are_empty_strings(): void
    {
        $obj = Helpers::getDefaultLocalesObject();
        foreach ($obj as $value) {
            $this->assertSame('', $value);
        }
    }

    public function test_translated_value_prefers_requested_locale(): void
    {
        $value = ['en' => 'English text', 'bg' => 'Български текст', 'gr' => 'Ελληνικά'];
        $this->assertSame('Български текст', Helpers::getTranslatedValue($value, 'bg'));
    }

    public function test_translated_value_falls_back_to_en_when_locale_empty(): void
    {
        $value = ['en' => 'Fallback', 'bg' => ''];
        $this->assertSame('Fallback', Helpers::getTranslatedValue($value, 'bg', true));
    }

    public function test_translated_value_returns_custom_fallback(): void
    {
        $this->assertSame('Default', Helpers::getTranslatedValue(null, 'en', true, 'Default'));
    }

    public function test_translated_value_non_array_passthrough(): void
    {
        $this->assertSame(42, Helpers::getTranslatedValue(42));
        $this->assertSame('hello', Helpers::getTranslatedValue('hello'));
    }

    public function test_translated_value_missing_locale_falls_to_english(): void
    {
        $value = ['en' => 'English only'];
        $this->assertSame('English only', Helpers::getTranslatedValue($value, 'fr'));
    }
}
