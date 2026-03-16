<?php

namespace Tests\Unit;

use App\Services\Helpers;
use PHPUnit\Framework\TestCase;

class TranslationFallbackTest extends TestCase
{
    public function test_returns_requested_locale(): void
    {
        $value = ['en' => 'Hello', 'nl' => 'Hallo'];
        $this->assertSame('Hallo', Helpers::getTranslatedValue($value, 'nl'));
    }

    public function test_falls_back_to_english_when_locale_empty(): void
    {
        $value = ['en' => 'Hello', 'nl' => ''];
        $this->assertSame('Hello', Helpers::getTranslatedValue($value, 'nl'));
    }

    public function test_returns_fallback_for_null_input(): void
    {
        $this->assertSame('default', Helpers::getTranslatedValue(null, 'en', true, 'default'));
    }

    public function test_returns_non_array_as_is(): void
    {
        $this->assertSame('plain', Helpers::getTranslatedValue('plain'));
    }

    public function test_without_escape_returns_raw(): void
    {
        $value = ['en' => 'English', 'nl' => ''];
        $result = Helpers::getTranslatedValue($value, 'nl', false);
        $this->assertSame('', $result);
    }

    public function test_missing_locale_falls_to_english(): void
    {
        $value = ['en' => 'English'];
        $this->assertSame('English', Helpers::getTranslatedValue($value, 'de'));
    }
}
