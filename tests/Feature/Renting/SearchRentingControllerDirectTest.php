<?php

namespace Tests\Feature\Renting;

use App\Files\CloudinaryService;
use App\Http\Controllers\SearchRentingController;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchRentingControllerDirectTest extends TestCase
{
    use DatabaseTransactions;
    use MocksRentingServices;

    protected function setUp(): void
    {
        try {
            DB::reconnect();
        } catch (\Throwable) {
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        try {
            DB::reconnect();
        } catch (\Throwable) {
        }
    }

    // ---------------------------------------------------------------
    // Request payloads
    // ---------------------------------------------------------------

    private function searchRentingData(array $overrides = []): array
    {
        return array_merge([
            'name'         => 'Jane',
            'surname'      => 'Smith',
            'phone'        => '+31698765432',
            'email'        => 'jane@example.com',
            'people'       => 2,
            'type'         => 'room',
            'moveIn'       => '2027-06-01',
            'period'       => '12 months',
            'registration' => 'true',
            'budget'       => 1200,
            'city'         => 'Amsterdam',
            'note'         => 'Looking for a quiet room',
            'interface'    => 'web',
            // terms always required when localhost is in terms_required_domains config
            'terms'        => json_encode(['contact' => true, 'legals' => true]),
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // create — POST /api/v1/renting/searching/create
    // ---------------------------------------------------------------

    public function test_create_fails_with_missing_required_fields(): void
    {
        $this->mockRentingCreateServices();

        $request = Request::create(
            '/api/v1/renting/searching/create',
            'POST',
            Arr::except($this->searchRentingData(), ['email', 'phone', 'city', 'type'])
        );

        $controller = app(SearchRentingController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_fails_with_invalid_email(): void
    {
        $this->mockRentingCreateServices();

        $request = Request::create(
            '/api/v1/renting/searching/create',
            'POST',
            $this->searchRentingData(['email' => 'not-an-email'])
        );

        $controller = app(SearchRentingController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_fails_with_invalid_interface(): void
    {
        $this->mockRentingCreateServices();

        $request = Request::create(
            '/api/v1/renting/searching/create',
            'POST',
            $this->searchRentingData(['interface' => 'fax'])
        );

        $controller = app(SearchRentingController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_creates_search_renting_with_valid_data(): void
    {
        $this->mockRentingCreateServices();

        $request = Request::create(
            '/api/v1/renting/searching/create',
            'POST',
            $this->searchRentingData()
        );

        $controller = app(SearchRentingController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);

        $this->assertDatabaseHas('search_rentings', [
            'email' => 'jane@example.com',
            'city'  => 'Amsterdam',
        ]);
    }
}
