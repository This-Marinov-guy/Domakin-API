<?php

namespace Tests\Feature\Renting;

use App\Constants\Properties;
use App\Files\CloudinaryService;
use App\Http\Controllers\SearchRentingController;
use App\Mail\Notification as NotificationMail;
use App\Models\Property;
use App\Models\PropertyData;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SearchRentingControllerDirectTest extends TestCase
{
    use DatabaseTransactions;
    use MocksRentingServices;

    protected function createProperty(): Property
    {
        return Property::create(['status' => 2, 'is_signal' => false]);
    }

    protected function createPropertyWithData(): Property
    {
        $property = $this->createProperty();

        PropertyData::create([
            'property_id' => $property->id,
            'city' => 'Amsterdam',
            'address' => 'Keizersgracht 1',
            'postcode' => '1015 CJ',
            'pets_allowed' => false,
            'smoking_allowed' => false,
            'size' => 20,
            'period' => '12 months',
            'rent' => '1200',
            'furnished_type' => 1,
            'bathrooms' => 1,
            'toilets' => 1,
            'available_from' => '2027-06-01',
            'bills' => 0,
            'deposit' => 1200,
            'flatmates' => '[]',
            'registration' => true,
            'description' => '{"en":"Nice room"}',
            'title' => 'Room in Amsterdam',
            'images' => 'room.jpg',
        ]);

        return $property->fresh(['propertyData']);
    }

    protected function setUp(): void
    {
        try {
            DB::reconnect();
        } catch (\Throwable) {
        }
        parent::setUp();
        Mail::fake();
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

    public function test_create_saves_property_id_when_provided(): void
    {
        $this->mockRentingCreateServices();
        $property = $this->createProperty();

        $request = Request::create(
            '/api/v1/renting/searching/create',
            'POST',
            $this->searchRentingData([
                'property_id' => $property->id + Properties::FRONTEND_PROPERTY_ID_INDEXING,
            ])
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
            'property_id' => $property->id,
        ]);
    }

    public function test_create_saves_property_id_when_legacy_property_id_field_is_used(): void
    {
        $this->mockRentingCreateServices();
        $property = $this->createProperty();

        $request = Request::create(
            '/api/v1/renting/searching/create',
            'POST',
            $this->searchRentingData([
                'propertyId' => $property->id + Properties::FRONTEND_PROPERTY_ID_INDEXING,
            ])
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
            'property_id' => $property->id,
        ]);
    }

    public function test_create_sends_renting_notification_when_linked_property_exists(): void
    {
        Mail::fake();
        $this->mockRentingCreateServices();
        $property = $this->createPropertyWithData();

        $request = Request::create(
            '/api/v1/renting/searching/create',
            'POST',
            $this->searchRentingData([
                'property_id' => $property->id + Properties::FRONTEND_PROPERTY_ID_INDEXING,
            ])
        );

        $controller = app(SearchRentingController::class);

        $response = $controller->create(
            $request,
            app(CloudinaryService::class),
            app(GoogleSheetsService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($property) {
            return $mail->templateUuid === 'renting'
                && $mail->subject === 'New renting request'
                && ($mail->data['property_id'] ?? null) === $property->id
                && ($mail->data['address'] ?? null) === 'Keizersgracht 1, Amsterdam';
        });
    }

    public function test_create_sends_search_renting_notification_when_no_linked_property_is_provided(): void
    {
        Mail::fake();
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

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) {
            return $mail->templateUuid === 'search_renting'
                && $mail->subject === 'New searching for Renting'
                && !array_key_exists('property', $mail->data)
                && !array_key_exists('address', $mail->data);
        });
    }
}
