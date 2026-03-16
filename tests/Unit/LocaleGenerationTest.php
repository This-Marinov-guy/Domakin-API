<?php

namespace Tests\Unit;

use App\Services\Helpers;
use App\Constants\Translations;
use PHPUnit\Framework\TestCase;

class LocaleGenerationTest extends TestCase
{
    public function test_json_is_valid(): void
    {
        $json = Helpers::getDefaultLocalesJSON();
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
    }

    public function test_json_keys_match_supported_locales(): void
    {
        $json = Helpers::getDefaultLocalesJSON();
        $decoded = json_decode($json, true);
        $this->assertSame(Translations::WEB_SUPPORTED_LOCALES, array_keys($decoded));
    }

    public function test_json_values_are_empty_strings(): void
    {
        $json = Helpers::getDefaultLocalesJSON();
        $decoded = json_decode($json, true);
        foreach ($decoded as $value) {
            $this->assertSame('', $value);
        }
    }

    public function test_object_keys_match_supported_locales(): void
    {
        $obj = Helpers::getDefaultLocalesObject();
        $this->assertSame(Translations::WEB_SUPPORTED_LOCALES, array_keys($obj));
    }

    public function test_object_values_are_empty(): void
    {
        $obj = Helpers::getDefaultLocalesObject();
        foreach ($obj as $value) {
            $this->assertSame('', $value);
        }
    }

    public function test_object_count_matches_locales(): void
    {
        $obj = Helpers::getDefaultLocalesObject();
        $this->assertCount(count(Translations::WEB_SUPPORTED_LOCALES), $obj);
    }
}
