<?php

namespace Tests\Unit;

use App\Services\Helpers;
use PHPUnit\Framework\TestCase;

class StreetExtractionTest extends TestCase
{
    public function test_simple_dutch_address(): void
    {
        $this->assertSame('Keizersgracht', Helpers::extractStreetName('Keizersgracht 123'));
    }

    public function test_address_with_letter_suffix(): void
    {
        $this->assertSame('Hoofdstraat', Helpers::extractStreetName('Hoofdstraat 12A'));
    }

    public function test_address_with_comma(): void
    {
        $result = Helpers::extractStreetName('Herengracht 500, Amsterdam');
        $this->assertStringContainsString('Herengracht', $result);
    }

    public function test_address_with_postcode(): void
    {
        $result = Helpers::extractStreetName('Vondelstraat 88, 1054 GE Amsterdam');
        $this->assertStringContainsString('Vondelstraat', $result);
    }

    public function test_long_street_name(): void
    {
        $result = Helpers::extractStreetName('Tweede Constantijn Huygensstraat 42');
        $this->assertStringContainsString('Constantijn', $result);
    }

    public function test_trims_whitespace(): void
    {
        $result = Helpers::extractStreetName('  Prinsengracht 33  ');
        $this->assertSame('Prinsengracht', $result);
    }

    public function test_complex_number_formats(): void
    {
        $result = Helpers::extractStreetName('Ruysdaelkade 221B, Amsterdam');
        $this->assertStringContainsString('Ruysdaelkade', $result);
    }
}
