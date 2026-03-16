<?php

namespace Tests\Unit;

use App\Services\Helpers;
use PHPUnit\Framework\TestCase;

class StringSplittingTest extends TestCase
{
    public function test_splits_comma_separated(): void
    {
        $items = [['tags' => 'wifi, parking, pool']];
        $result = Helpers::splitStringKeys($items, ['tags']);
        $this->assertSame(['wifi', 'parking', 'pool'], $result[0]['tags']);
    }

    public function test_single_value(): void
    {
        $items = [['category' => 'rental']];
        $result = Helpers::splitStringKeys($items, ['category']);
        $this->assertSame(['rental'], $result[0]['category']);
    }

    public function test_trims_whitespace(): void
    {
        $items = [['x' => ' a , b ']];
        $result = Helpers::splitStringKeys($items, ['x']);
        $this->assertSame(['a', 'b'], $result[0]['x']);
    }

    public function test_missing_key_unchanged(): void
    {
        $items = [['name' => 'test']];
        $result = Helpers::splitStringKeys($items, ['missing']);
        $this->assertSame([['name' => 'test']], $result);
    }

    public function test_multiple_items(): void
    {
        $items = [
            ['tags' => 'a, b'],
            ['tags' => 'c, d, e'],
        ];
        $result = Helpers::splitStringKeys($items, ['tags']);
        $this->assertCount(2, $result[0]['tags']);
        $this->assertCount(3, $result[1]['tags']);
    }

    public function test_nested_key_path(): void
    {
        $items = [['info' => ['amenities' => 'wifi, gym']]];
        $result = Helpers::splitStringKeys($items, ['info.amenities']);
        $this->assertSame(['wifi', 'gym'], $result[0]['info']['amenities']);
    }
}
