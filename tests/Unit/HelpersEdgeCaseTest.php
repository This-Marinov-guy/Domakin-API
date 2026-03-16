<?php

namespace Tests\Unit;

use App\Services\Helpers;
use PHPUnit\Framework\TestCase;

class HelpersEdgeCaseTest extends TestCase
{
    public function test_camel_to_snake_handles_consecutive_capitals(): void
    {
        $result = Helpers::camelToSnakeObject(['XMLParser' => 'test']);
        $this->assertArrayHasKey('x_m_l_parser', $result);
    }

    public function test_snake_to_camel_preserves_values(): void
    {
        $input = ['first_name' => 'John', 'age' => 25, 'is_active' => true];
        $result = Helpers::snakeToCamelObject($input);
        $this->assertSame('John', $result['firstName']);
        $this->assertSame(25, $result['age']);
        $this->assertTrue($result['isActive']);
    }

    public function test_extract_street_removes_postcode(): void
    {
        $result = Helpers::extractStreetName('Keizersgracht 123, 1015 Amsterdam');
        $this->assertStringContainsString('Keizersgracht', $result);
        $this->assertStringContainsString('Amsterdam', $result);
    }

    public function test_get_translated_value_with_all_empty_locales(): void
    {
        $value = ['en' => '', 'nl' => '', 'bg' => ''];
        $result = Helpers::getTranslatedValue($value, 'nl', true, 'fallback');
        $this->assertSame('fallback', $result);
    }

    public function test_decode_json_keys_with_nested_path(): void
    {
        $items = [
            ['meta' => ['tags' => '["a","b"]']],
        ];
        $result = Helpers::decodeJsonKeys($items, ['meta.tags']);
        $this->assertIsArray($result[0]['meta']['tags']);
        $this->assertSame(['a', 'b'], $result[0]['meta']['tags']);
    }

    public function test_split_string_single_value(): void
    {
        $items = [['category' => 'rental']];
        $result = Helpers::splitStringKeys($items, ['category']);
        $this->assertSame(['rental'], $result[0]['category']);
    }

    public function test_get_translated_value_without_escape_uses_direct_lookup(): void
    {
        $value = ['en' => 'English', 'nl' => 'Dutch'];
        $this->assertSame('Dutch', Helpers::getTranslatedValue($value, 'nl', false));
    }

    public function test_get_default_locales_json_is_valid_json(): void
    {
        $json = Helpers::getDefaultLocalesJSON();
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('en', $decoded);
    }

    public function test_get_default_locales_object_returns_array(): void
    {
        $result = Helpers::getDefaultLocalesObject();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('en', $result);
        $this->assertSame('', $result['en']);
    }
}
