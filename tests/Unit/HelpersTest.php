<?php

namespace Tests\Unit;

use App\Services\Helpers;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    // ---------------------------------------------------------------
    // camelToSnakeObject
    // ---------------------------------------------------------------

    public function test_camel_to_snake_converts_keys(): void
    {
        $result = Helpers::camelToSnakeObject([
            'firstName' => 'John',
            'lastName'  => 'Doe',
        ]);

        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertSame('John', $result['first_name']);
        $this->assertSame('Doe', $result['last_name']);
    }

    public function test_camel_to_snake_leaves_snake_case_unchanged(): void
    {
        $result = Helpers::camelToSnakeObject(['foo_bar' => 'baz']);
        $this->assertArrayHasKey('foo_bar', $result);
    }

    public function test_camel_to_snake_handles_empty_array(): void
    {
        $this->assertSame([], Helpers::camelToSnakeObject([]));
    }

    // ---------------------------------------------------------------
    // snakeToCamelObject
    // ---------------------------------------------------------------

    public function test_snake_to_camel_converts_keys(): void
    {
        $result = Helpers::snakeToCamelObject([
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
        ]);

        $this->assertArrayHasKey('firstName', $result);
        $this->assertArrayHasKey('lastName', $result);
        $this->assertSame('Jane', $result['firstName']);
    }

    public function test_snake_to_camel_handles_empty_array(): void
    {
        $this->assertSame([], Helpers::snakeToCamelObject([]));
    }

    public function test_snake_to_camel_round_trips_with_camel_to_snake(): void
    {
        $original = ['propertyId' => 42, 'listingType' => 'rent'];
        $snake    = Helpers::camelToSnakeObject($original);
        $back     = Helpers::snakeToCamelObject($snake);
        $this->assertSame($original, $back);
    }

    // ---------------------------------------------------------------
    // extractStreetName
    // ---------------------------------------------------------------

    public function test_extract_street_name_removes_house_number(): void
    {
        $this->assertSame('Keizersgracht', Helpers::extractStreetName('Keizersgracht 123'));
    }

    public function test_extract_street_name_removes_alphanumeric_tokens(): void
    {
        $this->assertSame('Hoofdstraat', Helpers::extractStreetName('Hoofdstraat 12A'));
    }

    public function test_extract_street_name_handles_comma_separated(): void
    {
        $result = Helpers::extractStreetName('Herengracht 500, Amsterdam');
        $this->assertStringContainsString('Herengracht', $result);
    }

    public function test_extract_street_name_returns_trimmed_string(): void
    {
        $result = Helpers::extractStreetName('  Vondelstraat 88  ');
        $this->assertSame('Vondelstraat', $result);
    }

    // ---------------------------------------------------------------
    // getTranslatedValue
    // ---------------------------------------------------------------

    public function test_get_translated_value_returns_locale_value(): void
    {
        $value = ['en' => 'Hello', 'nl' => 'Hallo', 'bg' => 'Здравей'];
        $this->assertSame('Hallo', Helpers::getTranslatedValue($value, 'nl'));
    }

    public function test_get_translated_value_falls_back_to_english(): void
    {
        $value = ['en' => 'Hello', 'nl' => ''];
        $this->assertSame('Hello', Helpers::getTranslatedValue($value, 'nl'));
    }

    public function test_get_translated_value_returns_fallback_for_empty(): void
    {
        $this->assertSame('N/A', Helpers::getTranslatedValue(null, 'en', true, 'N/A'));
        $this->assertSame('N/A', Helpers::getTranslatedValue([], 'en', true, 'N/A'));
    }

    public function test_get_translated_value_returns_non_array_as_is(): void
    {
        $this->assertSame('plain string', Helpers::getTranslatedValue('plain string'));
    }

    public function test_get_translated_value_without_escape_returns_null_locale(): void
    {
        $value = ['en' => 'English', 'nl' => null];
        $result = Helpers::getTranslatedValue($value, 'nl', false);
        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // decodeJsonKeys
    // ---------------------------------------------------------------

    public function test_decode_json_keys_decodes_string_to_array(): void
    {
        $items = [
            ['amenities' => '["wifi","parking"]', 'title' => 'Flat'],
        ];

        $result = Helpers::decodeJsonKeys($items, ['amenities']);

        $this->assertIsArray($result[0]['amenities']);
        $this->assertContains('wifi', $result[0]['amenities']);
    }

    public function test_decode_json_keys_leaves_non_json_unchanged(): void
    {
        $items = [['title' => 'Not JSON']];
        $result = Helpers::decodeJsonKeys($items, ['title']);
        $this->assertSame('Not JSON', $result[0]['title']);
    }

    public function test_decode_json_keys_handles_missing_key(): void
    {
        $items = [['name' => 'test']];
        $result = Helpers::decodeJsonKeys($items, ['missing_key']);
        $this->assertSame([['name' => 'test']], $result);
    }

    // ---------------------------------------------------------------
    // splitStringKeys
    // ---------------------------------------------------------------

    public function test_split_string_keys_splits_comma_separated(): void
    {
        $items = [['tags' => 'wifi, parking, pool']];
        $result = Helpers::splitStringKeys($items, ['tags']);
        $this->assertSame(['wifi', 'parking', 'pool'], $result[0]['tags']);
    }

    public function test_split_string_keys_trims_whitespace(): void
    {
        $items = [['tags' => '  wifi ,  pool  ']];
        $result = Helpers::splitStringKeys($items, ['tags']);
        $this->assertSame(['wifi', 'pool'], $result[0]['tags']);
    }

    public function test_split_string_keys_handles_missing_key(): void
    {
        $items = [['name' => 'test']];
        $result = Helpers::splitStringKeys($items, ['missing']);
        $this->assertSame([['name' => 'test']], $result);
    }
}
