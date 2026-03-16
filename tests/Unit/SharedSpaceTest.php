<?php

namespace Tests\Unit;

use App\Enums\SharedSpace;
use PHPUnit\Framework\TestCase;

class SharedSpaceTest extends TestCase
{
    public function test_get_label_returns_known_labels(): void
    {
        $this->assertSame('Balcony', SharedSpace::getLabel(0));
        $this->assertSame('Kitchen', SharedSpace::getLabel(1));
        $this->assertSame('Bathroom', SharedSpace::getLabel(2));
        $this->assertSame('Toilet', SharedSpace::getLabel(3));
        $this->assertSame('Storage space', SharedSpace::getLabel(4));
        $this->assertSame('Living room', SharedSpace::getLabel(5));
    }

    public function test_get_label_returns_fallback_for_unknown(): void
    {
        $this->assertSame('#99', SharedSpace::getLabel(99));
    }

    public function test_get_labels_for_value_single(): void
    {
        $this->assertSame('Kitchen', SharedSpace::getLabelsForValue('1'));
    }

    public function test_get_labels_for_value_multiple(): void
    {
        $this->assertSame('Balcony, Kitchen, Living room', SharedSpace::getLabelsForValue('0,1,5'));
    }

    public function test_get_labels_for_value_null(): void
    {
        $this->assertSame('', SharedSpace::getLabelsForValue(null));
    }

    public function test_get_labels_for_value_empty(): void
    {
        $this->assertSame('', SharedSpace::getLabelsForValue(''));
    }

    public function test_labels_constant_has_six_entries(): void
    {
        $this->assertCount(6, SharedSpace::LABELS);
    }
}
