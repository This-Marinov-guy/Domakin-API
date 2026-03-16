<?php

namespace Tests\Unit;

use App\Services\Helpers;
use PHPUnit\Framework\TestCase;

class JsonDecodingTest extends TestCase
{
    public function test_decodes_simple_json_array(): void
    {
        $items = [['tags' => '["wifi","parking"]']];
        $result = Helpers::decodeJsonKeys($items, ['tags']);
        $this->assertSame(['wifi', 'parking'], $result[0]['tags']);
    }

    public function test_decodes_json_object(): void
    {
        $items = [['meta' => '{"key":"value"}']];
        $result = Helpers::decodeJsonKeys($items, ['meta']);
        $this->assertSame(['key' => 'value'], $result[0]['meta']);
    }

    public function test_leaves_invalid_json_untouched(): void
    {
        $items = [['name' => 'not json']];
        $result = Helpers::decodeJsonKeys($items, ['name']);
        $this->assertSame('not json', $result[0]['name']);
    }

    public function test_handles_multiple_items(): void
    {
        $items = [
            ['tags' => '["a"]'],
            ['tags' => '["b","c"]'],
        ];
        $result = Helpers::decodeJsonKeys($items, ['tags']);
        $this->assertSame(['a'], $result[0]['tags']);
        $this->assertSame(['b', 'c'], $result[1]['tags']);
    }

    public function test_nested_key_path(): void
    {
        $items = [['data' => ['amenities' => '["pool"]']]];
        $result = Helpers::decodeJsonKeys($items, ['data.amenities']);
        $this->assertSame(['pool'], $result[0]['data']['amenities']);
    }

    public function test_missing_nested_key(): void
    {
        $items = [['name' => 'test']];
        $result = Helpers::decodeJsonKeys($items, ['data.amenities']);
        $this->assertSame([['name' => 'test']], $result);
    }
}
