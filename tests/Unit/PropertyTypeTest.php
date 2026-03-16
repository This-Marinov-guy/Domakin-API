<?php

namespace Tests\Unit;

use App\Enums\PropertyType;
use PHPUnit\Framework\TestCase;

class PropertyTypeTest extends TestCase
{
    public function test_room_in_shared_property(): void
    {
        $this->assertSame(1, PropertyType::RoomInSharedProperty->value);
        $this->assertSame('Room in a shared property', PropertyType::RoomInSharedProperty->label());
    }

    public function test_studio(): void
    {
        $this->assertSame(2, PropertyType::Studio->value);
        $this->assertSame('Studio', PropertyType::Studio->label());
    }

    public function test_apartment(): void
    {
        $this->assertSame(3, PropertyType::Apartment->value);
        $this->assertSame('Apartment', PropertyType::Apartment->label());
    }

    public function test_house(): void
    {
        $this->assertSame(4, PropertyType::House->value);
        $this->assertSame('House', PropertyType::House->label());
    }

    public function test_try_label_returns_null_for_null(): void
    {
        $this->assertNull(PropertyType::tryLabel(null));
    }

    public function test_try_label_returns_null_for_invalid_value(): void
    {
        $this->assertNull(PropertyType::tryLabel(999));
    }

    public function test_try_label_returns_label_for_valid_value(): void
    {
        $this->assertSame('Studio', PropertyType::tryLabel(2));
    }

    public function test_all_values_are_unique(): void
    {
        $values = array_map(fn($c) => $c->value, PropertyType::cases());
        $this->assertCount(count($values), array_unique($values));
    }
}
