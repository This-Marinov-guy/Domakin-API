<?php

namespace Tests\Unit;

use App\Services\Helpers;
use PHPUnit\Framework\TestCase;

class CaseConversionTest extends TestCase
{
    public function test_camel_to_snake_single_word(): void
    {
        $result = Helpers::camelToSnakeObject(['name' => 'test']);
        $this->assertSame(['name' => 'test'], $result);
    }

    public function test_camel_to_snake_multiple_words(): void
    {
        $result = Helpers::camelToSnakeObject([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dateOfBirth' => '1990-01-01',
        ]);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayHasKey('date_of_birth', $result);
    }

    public function test_snake_to_camel_single_word(): void
    {
        $result = Helpers::snakeToCamelObject(['name' => 'test']);
        $this->assertSame(['name' => 'test'], $result);
    }

    public function test_snake_to_camel_multiple_words(): void
    {
        $result = Helpers::snakeToCamelObject([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_of_birth' => '1990-01-01',
        ]);
        $this->assertArrayHasKey('firstName', $result);
        $this->assertArrayHasKey('lastName', $result);
        $this->assertArrayHasKey('dateOfBirth', $result);
    }

    public function test_round_trip_conversion(): void
    {
        $original = [
            'userId' => 1,
            'firstName' => 'John',
            'isActive' => true,
        ];
        $snake = Helpers::camelToSnakeObject($original);
        $camel = Helpers::snakeToCamelObject($snake);
        $this->assertSame($original, $camel);
    }

    public function test_preserves_values_through_conversion(): void
    {
        $input = [
            'someNumber' => 42,
            'someArray' => [1, 2, 3],
            'someNull' => null,
            'someBool' => false,
        ];
        $result = Helpers::camelToSnakeObject($input);
        $this->assertSame(42, $result['some_number']);
        $this->assertSame([1, 2, 3], $result['some_array']);
        $this->assertNull($result['some_null']);
        $this->assertFalse($result['some_bool']);
    }
}
