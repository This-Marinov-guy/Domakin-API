<?php

namespace Tests\Unit;

use App\Enums\FurnishedType;
use PHPUnit\Framework\TestCase;

class FurnishedTypeTest extends TestCase
{
    public function test_fully_furnished(): void
    {
        $this->assertSame(1, FurnishedType::FullyFurnished->value);
        $this->assertSame('Fully furnished', FurnishedType::FullyFurnished->label());
    }

    public function test_semi_furnished(): void
    {
        $this->assertSame(2, FurnishedType::SemiFurnished->value);
        $this->assertSame('Semi-furnished', FurnishedType::SemiFurnished->label());
    }

    public function test_none(): void
    {
        $this->assertSame(3, FurnishedType::None->value);
        $this->assertSame('None', FurnishedType::None->label());
    }

    public function test_try_label_returns_null_for_null(): void
    {
        $this->assertNull(FurnishedType::tryLabel(null));
    }

    public function test_try_label_returns_label_for_valid(): void
    {
        $this->assertSame('Fully furnished', FurnishedType::tryLabel(1));
    }

    public function test_try_label_returns_null_for_invalid(): void
    {
        $this->assertNull(FurnishedType::tryLabel(99));
    }
}
