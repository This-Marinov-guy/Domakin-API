<?php

namespace Tests\Feature\Property;

use App\Files\CloudinaryService;
use App\Http\Controllers\PropertyController;
use App\Jobs\ReformatPropertyDescriptionJob;
use App\Models\Property;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\Integrations\SignalIntegrationService;
use App\Services\ListingMailerService;
use App\Services\Payment\PaymentLinkService;
use App\Services\PropertyService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PropertyControllerDirectTest extends TestCase
{
    use DatabaseTransactions;
    use MocksPropertyServices;
    use PropertyControllerData;

    // ---------------------------------------------------------------
    // show — GET /api/v1/property/listing
    // ---------------------------------------------------------------

    public function test_show_direct_returns_successful_response(): void
    {
        // Mock the service so pre-existing DB rows with incomplete data can't crash the test.
        $this->mock(PropertyService::class, fn($m) =>
            $m->shouldReceive('parsePropertiesForListing')
                ->once()
                ->andReturn([['id' => 1001, 'price' => '850', 'status' => 'rent']])
        );

        $request = Request::create('/api/v1/property/listing', 'GET');

        /** @var PropertyController $controller */
        $controller = app(PropertyController::class);

        $response = $controller->show($request, app(PropertyService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(1001, $payload['data'][0]['id']);
    }

    public function test_show_direct_passes_accept_language_to_service(): void
    {
        $this->mock(PropertyService::class, function ($mock) {
            $mock->shouldReceive('parsePropertiesForListing')
                ->withArgs(fn($properties, $lang) => $lang === 'bg')
                ->once()
                ->andReturn([]);
        });

        $request = Request::create('/api/v1/property/listing', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'bg-BG,bg;q=0.9,en;q=0.8',
        ]);

        $controller = app(PropertyController::class);

        $response = $controller->show($request, app(PropertyService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
    }

    // ---------------------------------------------------------------
    // details — GET /api/v1/property/details/{id}
    // ---------------------------------------------------------------

    public function test_details_direct_returns_property_data_for_valid_id(): void
    {
        $property = $this->createPropertyWithRelations();

        /** @var PropertyController $controller */
        $controller = app(PropertyController::class);

        $response = $controller->details($property->id);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('vlady1002@abv.bg', $payload['data']['personal_data']['email']);
        $this->assertSame('Amsterdam', $payload['data']['property_data']['city']);
    }

    // ---------------------------------------------------------------
    // create — POST /api/v1/property/create
    // ---------------------------------------------------------------

    public function test_create_direct_fails_with_missing_required_fields(): void
    {
        // CloudinaryService constructor calls `new Cloudinary()` — mock to prevent SDK init issues.
        $this->mockCloudinaryService();

        $request = Request::create(
            '/api/v1/property/create',
            'POST',
            $this->without($this->createRequestData(), 'interface')
        );

        $controller = app(PropertyController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class),
            app(PropertyService::class),
            app(UserService::class),
            app(PaymentLinkService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_direct_fails_with_invalid_personal_data(): void
    {
        $this->mockCloudinaryService();

        $request = Request::create(
            '/api/v1/property/create',
            'POST',
            [
                'personalData' => json_encode(['name' => 'Vladislav']), // missing surname, email, phone
                'propertyData' => json_encode($this->propertyDataArray()),
                'interface'    => 'web',
                'terms'        => 'null',
            ]
        );

        $controller = app(PropertyController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class),
            app(PropertyService::class),
            app(UserService::class),
            app(PaymentLinkService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_direct_creates_property_with_valid_data(): void
    {
        Queue::fake();

        $this->mockCreateServices();

        $request = Request::create(
            '/api/v1/property/create',
            'POST',
            $this->createRequestData()
        );
        $request->files->set('newImages', [UploadedFile::fake()->image('room.jpg')]);

        $controller = app(PropertyController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class),
            app(PropertyService::class),
            app(UserService::class),
            app(PaymentLinkService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);

        Queue::assertPushed(ReformatPropertyDescriptionJob::class);
    }

    // ---------------------------------------------------------------
    // edit — POST /api/v1/property/edit
    // ---------------------------------------------------------------

    public function test_edit_direct_returns_error_for_unknown_property(): void
    {
        $this->mockUserService();

        $request = Request::create(
            '/api/v1/property/edit',
            'POST',
            $this->editRequestData(999999)
        );

        $controller = app(PropertyController::class);

        $response = $controller->edit(
            $request,
            app(PropertyService::class),
            app(UserService::class),
            app(PaymentLinkService::class),
            app(CloudinaryService::class),
            app(SignalIntegrationService::class)
        );

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_edit_direct_fails_validation_with_missing_required_fields(): void
    {
        $property = $this->createPropertyWithRelations();

        $this->mockUserService();

        $request = Request::create(
            '/api/v1/property/edit',
            'POST',
            [
                'id'           => $property->id,
                'propertyData' => json_encode($this->without($this->editPropertyDataArray(), 'city', 'address')),
                'status'       => 1,
            ]
        );

        $controller = app(PropertyController::class);

        $response = $controller->edit(
            $request,
            app(PropertyService::class),
            app(UserService::class),
            app(PaymentLinkService::class),
            app(CloudinaryService::class),
            app(SignalIntegrationService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_edit_direct_updates_property_with_valid_data(): void
    {
        $property = $this->createPropertyWithRelations();

        $this->mockEditServices();

        $request = Request::create(
            '/api/v1/property/edit',
            'POST',
            $this->editRequestData($property->id)
        );

        $controller = app(PropertyController::class);

        $response = $controller->edit(
            $request,
            app(PropertyService::class),
            app(UserService::class),
            app(PaymentLinkService::class),
            app(CloudinaryService::class),
            app(SignalIntegrationService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('Property updated successfully', $payload['data']['message']);
    }

    // ---------------------------------------------------------------
    // delete — DELETE /api/v1/property/delete
    // ---------------------------------------------------------------

    public function test_delete_direct_returns_error_for_unknown_property(): void
    {
        $this->mockSignalIntegrationService();

        $request = Request::create('/api/v1/property/delete', 'DELETE', ['id' => 999999]);

        $controller = app(PropertyController::class);

        $response = $controller->delete($request, app(SignalIntegrationService::class));

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_delete_direct_deletes_existing_property(): void
    {
        $property = $this->createPropertyWithRelations();

        $this->mockSignalIntegrationService();

        $request = Request::create('/api/v1/property/delete', 'DELETE', ['id' => $property->id]);

        $controller = app(PropertyController::class);

        $response = $controller->delete($request, app(SignalIntegrationService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertNull(Property::find($property->id));
    }

    // ---------------------------------------------------------------
    // fetchUserProperties — GET /api/v1/property/list
    // ---------------------------------------------------------------

    public function test_fetch_user_properties_direct_returns_paginated_list(): void
    {
        $this->mockUserService(self::TEST_USER_UUID);

        $this->createPropertyWithRelations(['created_by' => self::TEST_USER_UUID]);

        $request = Request::create('/api/v1/property/list', 'GET');

        $controller = app(PropertyController::class);

        $response = $controller->fetchUserProperties(
            $request,
            app(UserService::class),
            app(PropertyService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertArrayHasKey('properties', $payload['data']);
        $this->assertNotEmpty($payload['data']['properties']);
    }

    // ---------------------------------------------------------------
    // fetchAllProperties — GET /api/v1/property/list-extended
    // ---------------------------------------------------------------

    public function test_fetch_all_properties_direct_returns_paginated_list(): void
    {
        $this->createPropertyWithRelations();

        $request = Request::create('/api/v1/property/list-extended', 'GET');

        $controller = app(PropertyController::class);

        $response = $controller->fetchAllProperties($request, app(PropertyService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertArrayHasKey('properties', $payload['data']);
    }

    // ---------------------------------------------------------------
    // createPaymentLink — POST /api/v1/property/payment/create-link
    // ---------------------------------------------------------------

    public function test_create_payment_link_direct_returns_error_for_unknown_property(): void
    {
        $request = Request::create(
            '/api/v1/property/payment/create-link',
            'POST',
            ['id' => 999999]
        );

        $controller = app(PropertyController::class);

        $response = $controller->createPaymentLink($request, app(PaymentLinkService::class));

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_create_payment_link_direct_returns_error_for_zero_rent(): void
    {
        $property = $this->createPropertyWithRelations();
        $property->propertyData->update(['rent' => '0']);

        $request = Request::create(
            '/api/v1/property/payment/create-link',
            'POST',
            ['id' => $property->id]
        );

        $controller = app(PropertyController::class);

        $response = $controller->createPaymentLink($request, app(PaymentLinkService::class));

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_create_payment_link_direct_returns_payment_link_for_valid_property(): void
    {
        $property = $this->createPropertyWithRelations();

        $this->mock(PaymentLinkService::class, function ($mock) {
            $mock->shouldReceive('createPropertyFeeLink')
                ->andReturn('https://stripe.com/pay/abc123');
        });

        $request = Request::create(
            '/api/v1/property/payment/create-link',
            'POST',
            ['id' => $property->id]
        );

        $controller = app(PropertyController::class);

        $response = $controller->createPaymentLink($request, app(PaymentLinkService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('https://stripe.com/pay/abc123', $payload['data']['payment_link']);
    }
}
