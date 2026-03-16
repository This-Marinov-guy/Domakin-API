<?php

namespace Tests\Unit;

use App\Enums\PropertyStatus;
use PHPUnit\Framework\TestCase;

class PropertyStatusLabelTest extends TestCase
{
    public function test_pending_label(): void
    {
        $this->assertSame('pending', PropertyStatus::Pending->label());
    }

    public function test_rent_label(): void
    {
        $this->assertSame('rent', PropertyStatus::Rent->label());
    }

    public function test_taken_label(): void
    {
        $this->assertSame('taken', PropertyStatus::Taken->label());
    }

    public function test_all_statuses_have_non_empty_labels(): void
    {
        foreach (PropertyStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
        }
    }

    public function test_label_returns_lowercase_string(): void
    {
        foreach (PropertyStatus::cases() as $status) {
            $this->assertMatchesRegularExpression('/^[a-z]+$/', $status->label());
        }
    }
}
