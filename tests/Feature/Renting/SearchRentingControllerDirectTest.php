<?php

namespace Tests\Feature\Renting;

use App\Constants\Properties;
use App\Files\CloudinaryService;
use App\Http\Controllers\SearchRentingController;
use App\Jobs\ExportModelToSpreadsheetJob;
use App\Jobs\SendInternalNotificationJob;
use App\Jobs\SendSearchRentingMailerJob;
use App\Models\Property;
use App\Models\PropertyData;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\MailerApiService;
use App\Services\SearchRentingMailerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
            'period' => '{"en":"12 months"}',
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
            'title' => '{"en":"Room in Amsterdam"}',
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
        Queue::fake();
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

        Queue::assertPushed(SendInternalNotificationJob::class, function (SendInternalNotificationJob $job) use ($property) {
            return $job->templateUuid === 'renting'
                && $job->subjectLine === 'New renting request'
                && ($job->data['property_id'] ?? null) === $property->id
                && ($job->data['address'] ?? null) === 'Keizersgracht 1, Amsterdam';
        });

        Queue::assertPushed(ExportModelToSpreadsheetJob::class, function (ExportModelToSpreadsheetJob $job) {
            return $job->modelClass === \App\Models\SearchRenting::class
                && $job->sheetName === 'Search Rentings';
        });

        Queue::assertPushed(SendSearchRentingMailerJob::class);
    }

    public function test_create_sends_search_renting_notification_when_no_linked_property_is_provided(): void
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

        Queue::assertPushed(SendInternalNotificationJob::class, function (SendInternalNotificationJob $job) {
            return $job->templateUuid === 'search_renting'
                && $job->subjectLine === 'New searching for Renting'
                && !array_key_exists('property', $job->data)
                && !array_key_exists('address', $job->data);
        });
    }

    public function test_search_renting_mailer_sends_room_searching_applied_payload(): void
    {
        $searchRenting = \App\Models\SearchRenting::create([
            'name' => 'Jane',
            'surname' => 'Smith',
            'phone' => '+31698765432',
            'email' => 'jane@example.com',
            'people' => 2,
            'type' => 'room',
            'move_in' => '2027-06-01',
            'period' => '12 months',
            'registration' => 'true',
            'budget' => 1200,
            'city' => 'Amsterdam',
            'locale' => 'bg',
            'interface' => 'web',
        ]);

        $this->mock(MailerApiService::class, function ($mock) use ($searchRenting) {
            $mock->shouldReceive('post')
                ->once()
                ->with('/room/send-room-searching-applied', \Mockery::on(function (array $payload) use ($searchRenting) {
                    return $payload['email'] === 'jane@example.com'
                        && $payload['id'] === (string) $searchRenting->id
                        && $payload['language'] === 'bg';
                }))
                ->andReturn(['ok' => true]);
        });

        app(SearchRentingMailerService::class)->sendRoomSearchingApplied($searchRenting);
    }

    public function test_search_renting_applied_email_endpoint_supports_direct_email_payload(): void
    {
        $this->mock(MailerApiService::class, function ($mock) {
            $mock->shouldReceive('post')
                ->once()
                ->with('/room/send-room-searching-applied', \Mockery::on(fn (array $payload) =>
                    $payload['email'] === 'info@domaki.nl'
                    && $payload['id'] === ''
                    && $payload['language'] === 'en'
                ))
                ->andReturn(['ok' => true]);
        });

        $request = Request::create('/api/v1/renting/searching/send-applied-email', 'POST', [
            'email' => 'info@domaki.nl',
            'language' => 'en',
        ]);

        $response = app(SearchRentingController::class)->sendAppliedEmail($request, app(SearchRentingMailerService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
    }
}
