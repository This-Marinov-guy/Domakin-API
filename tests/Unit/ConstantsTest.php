<?php

namespace Tests\Unit;

use App\Constants\Translations;
use App\Enums\PropertyStatus;
use App\Enums\Roles;
use PHPUnit\Framework\TestCase;

class ConstantsTest extends TestCase
{
    // ---------------------------------------------------------------
    // Translations
    // ---------------------------------------------------------------

    public function test_translations_supported_locales_is_non_empty_array(): void
    {
        $locales = Translations::WEB_SUPPORTED_LOCALES;
        $this->assertIsArray($locales);
        $this->assertNotEmpty($locales);
    }

    public function test_translations_supported_locales_contains_english(): void
    {
        $this->assertContains('en', Translations::WEB_SUPPORTED_LOCALES);
    }

    public function test_translations_locales_are_two_char_codes(): void
    {
        foreach (Translations::WEB_SUPPORTED_LOCALES as $locale) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]{2}$/',
                $locale,
                "Locale '{$locale}' is not a valid 2-char code"
            );
        }
    }

    // ---------------------------------------------------------------
    // PropertyStatus enum
    // ---------------------------------------------------------------

    public function test_property_status_has_expected_cases(): void
    {
        $cases = PropertyStatus::cases();
        $this->assertNotEmpty($cases);

        $names = array_map(fn($c) => $c->name, $cases);
        $this->assertContains('Pending', $names);
        $this->assertContains('Rent', $names);
        $this->assertContains('Taken', $names);
    }

    public function test_property_status_values_are_positive_integers(): void
    {
        foreach (PropertyStatus::cases() as $case) {
            $this->assertIsInt($case->value);
            $this->assertGreaterThan(0, $case->value);
        }
    }

    public function test_property_status_values_are_unique(): void
    {
        $values = array_map(fn($c) => $c->value, PropertyStatus::cases());
        $this->assertCount(count($values), array_unique($values));
    }

    public function test_property_status_label_returns_string(): void
    {
        foreach (PropertyStatus::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_property_status_can_be_created_from_value(): void
    {
        $this->assertSame(PropertyStatus::Pending, PropertyStatus::from(1));
        $this->assertSame(PropertyStatus::Rent,    PropertyStatus::from(2));
        $this->assertSame(PropertyStatus::Taken,   PropertyStatus::from(3));
    }

    // ---------------------------------------------------------------
    // Roles enum
    // ---------------------------------------------------------------

    public function test_roles_has_expected_cases(): void
    {
        $names = array_map(fn($c) => $c->value, Roles::cases());
        $this->assertContains('admin', $names);
        $this->assertContains('user', $names);
        $this->assertContains('editor', $names);
        $this->assertContains('agent', $names);
    }

    public function test_roles_values_are_unique(): void
    {
        $values = array_map(fn($c) => $c->value, Roles::cases());
        $this->assertCount(count($values), array_unique($values));
    }

    public function test_roles_can_be_created_from_string(): void
    {
        $this->assertSame(Roles::ADMIN, Roles::from('admin'));
        $this->assertSame(Roles::USER,  Roles::from('user'));
    }
}
