<?php

namespace Tests\Unit;

use App\Enums\Amenities;
use PHPUnit\Framework\TestCase;

class AmenitiesEnumTest extends TestCase
{
    public function test_amenities_class_exists(): void
    {
        $this->assertTrue(class_exists(Amenities::class) || enum_exists(Amenities::class));
    }
}
