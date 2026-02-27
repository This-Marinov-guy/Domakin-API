<?php

namespace Tests\Feature\Renting;

use App\Files\CloudinaryService;
use App\Http\Controllers\RentingController;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\RentingService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RentingControllerDirectTest extends TestCase
{
    use DatabaseTransactions;
    use MocksRentingServices;
    use RentingControllerData;

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
    // create — POST /api/v1/renting/create
    // ---------------------------------------------------------------

    public function test_create_fails_with_missing_required_fields(): void
    {
        $this->mockRentingCreateServices();

        $request = Request::create('/api/v1/renting/create', 'POST', [
            'name' => 'John',
            // missing: surname, phone, email, interface
        ]);

        $controller = app(RentingController::class);

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

        $property = $this->createProperty();

        $request = Request::create(
            '/api/v1/renting/create',
            'POST',
            $this->rentingCreateData($property->id, ['email' => 'not-a-valid-email'])
        );

        $controller = app(RentingController::class);

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

        $property = $this->createProperty();

        $request = Request::create(
            '/api/v1/renting/create',
            'POST',
            $this->rentingCreateData($property->id, ['interface' => 'fax'])
        );

        $controller = app(RentingController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_creates_renting_with_valid_data(): void
    {
        $this->mockRentingCreateServices();

        $property = $this->createProperty();

        $request = Request::create(
            '/api/v1/renting/create',
            'POST',
            $this->rentingCreateData($property->id)
        );

        $controller = app(RentingController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);

        $this->assertDatabaseHas('rentings', [
            'property_id' => $property->id,
            'email'       => 'john@example.com',
        ]);
    }

    // ---------------------------------------------------------------
    // show — GET /api/v1/renting/{id}
    // ---------------------------------------------------------------

    public function test_show_returns_error_for_unknown_property(): void
    {
        $controller = app(RentingController::class);

        $response = $controller->show(999999);

        // ApiResponseClass::sendError($message, $tag, $code=400) — second arg is the tag field,
        // not the HTTP status code; the response HTTP code is always 400 by default.
        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_show_returns_rentings_for_valid_property(): void
    {
        $property = $this->createProperty();
        $this->createRenting($property->id);
        $this->createRenting($property->id);

        $controller = app(RentingController::class);

        $response = $controller->show($property->id);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertCount(2, $payload['data']);
    }

    // ---------------------------------------------------------------
    // list — GET /api/v1/renting/list
    // ---------------------------------------------------------------

    public function test_list_fails_without_property_id(): void
    {
        $request = Request::create('/api/v1/renting/list', 'GET');

        $controller = app(RentingController::class);

        $response = $controller->list($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_list_fails_with_unknown_property_id(): void
    {
        $request = Request::create('/api/v1/renting/list', 'GET', ['property_id' => 999999]);

        $controller = app(RentingController::class);

        $response = $controller->list($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_list_returns_paginated_rentings(): void
    {
        $property = $this->createProperty();
        $this->createRenting($property->id);
        $this->createRenting($property->id);

        $request = Request::create('/api/v1/renting/list', 'GET', [
            'property_id' => $property->id,
            'per_page'    => 5,
            'page'        => 1,
        ]);

        $controller = app(RentingController::class);

        $response = $controller->list($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertArrayHasKey('rentings', $payload['data']);
        $this->assertArrayHasKey('total', $payload['data']);
        $this->assertGreaterThanOrEqual(2, $payload['data']['total']);
    }

    // ---------------------------------------------------------------
    // edit — PATCH /api/v1/renting/edit
    // ---------------------------------------------------------------

    public function test_edit_fails_with_missing_id(): void
    {
        $this->mockUserService();

        $request = Request::create('/api/v1/renting/edit', 'PATCH', [
            'status' => 2,
        ]);

        $controller = app(RentingController::class);

        $response = $controller->edit(
            $request,
            app(RentingService::class),
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_edit_fails_with_nonexistent_renting_id(): void
    {
        // The validation rule 'id' => 'exists:rentings,id' rejects non-existent IDs with 422
        // before the controller can return a 404, so this is the correct assertion.
        $this->mockUserService();

        $request = Request::create('/api/v1/renting/edit', 'PATCH', [
            'id'     => 999999,
            'status' => 2,
        ]);

        $controller = app(RentingController::class);

        $response = $controller->edit(
            $request,
            app(RentingService::class),
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_edit_updates_renting_with_valid_data(): void
    {
        $this->mockUserService();

        $property = $this->createProperty();
        $renting  = $this->createRenting($property->id);

        $request = Request::create(
            '/api/v1/renting/edit',
            'PATCH',
            $this->rentingEditData($renting->id)
        );

        $controller = app(RentingController::class);

        $response = $controller->edit(
            $request,
            app(RentingService::class),
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(2, $payload['data']['status']);
        $this->assertSame('Reviewed by admin', $payload['data']['internal_note']);
    }
}
