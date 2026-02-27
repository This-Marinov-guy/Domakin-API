<?php

namespace Tests\Feature\Viewing;

use App\Http\Controllers\ViewingController;
use App\Models\Viewing;
use App\Services\GoogleServices\GoogleCalendarService;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ViewingControllerDirectTest extends TestCase
{
    use DatabaseTransactions;
    use MocksViewingServices;
    use ViewingControllerData;

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
    // list — GET /api/v1/viewing/list
    // ---------------------------------------------------------------

    public function test_list_returns_all_viewings(): void
    {
        $this->createViewing(['email' => 'a@example.com']);
        $this->createViewing(['email' => 'b@example.com']);

        $controller = app(ViewingController::class);

        $response = $controller->list();

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertIsArray($payload['data']);
        $this->assertGreaterThanOrEqual(2, count($payload['data']));
    }

    // ---------------------------------------------------------------
    // details — GET /api/v1/viewing/details/{id}
    // ---------------------------------------------------------------

    public function test_details_returns_400_for_unknown_viewing(): void
    {
        $controller = app(ViewingController::class);

        $response = $controller->details(999999);

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_details_returns_viewing_for_valid_id(): void
    {
        $viewing = $this->createViewing();

        $controller = app(ViewingController::class);

        $response = $controller->details($viewing->id);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('john@example.com', $payload['data']['email']);
        $this->assertSame('Amsterdam', $payload['data']['city']);
    }

    // ---------------------------------------------------------------
    // create — POST /api/v1/viewing/create
    // ---------------------------------------------------------------

    public function test_create_fails_with_missing_required_fields(): void
    {
        $this->mockViewingCreateServices();

        $request = Request::create(
            '/api/v1/viewing/create',
            'POST',
            $this->without($this->viewingCreateData(), 'email', 'phone', 'city', 'address')
        );

        $controller = app(ViewingController::class);

        $response = $controller->create(
            $request,
            app(GoogleSheetsService::class),
            app(GoogleCalendarService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_fails_with_invalid_email(): void
    {
        $this->mockViewingCreateServices();

        $request = Request::create(
            '/api/v1/viewing/create',
            'POST',
            $this->viewingCreateData(['email' => 'not-an-email'])
        );

        $controller = app(ViewingController::class);

        $response = $controller->create(
            $request,
            app(GoogleSheetsService::class),
            app(GoogleCalendarService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_fails_with_invalid_interface(): void
    {
        $this->mockViewingCreateServices();

        $request = Request::create(
            '/api/v1/viewing/create',
            'POST',
            $this->viewingCreateData(['interface' => 'fax'])
        );

        $controller = app(ViewingController::class);

        $response = $controller->create(
            $request,
            app(GoogleSheetsService::class),
            app(GoogleCalendarService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_creates_viewing_with_valid_data(): void
    {
        $this->mockViewingCreateServices();

        $request = Request::create(
            '/api/v1/viewing/create',
            'POST',
            $this->viewingCreateData()
        );

        $controller = app(ViewingController::class);

        $response = $controller->create(
            $request,
            app(GoogleSheetsService::class),
            app(GoogleCalendarService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);

        $this->assertDatabaseHas('viewings', [
            'email' => 'john@example.com',
            'city'  => 'Amsterdam',
        ]);
    }

    public function test_create_attaches_google_calendar_id_to_viewing(): void
    {
        $this->mockGoogleSheetsService();
        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')
                ->once()
                ->andReturn('cal-event-abc123');
        });

        $request = Request::create(
            '/api/v1/viewing/create',
            'POST',
            $this->viewingCreateData()
        );

        $controller = app(ViewingController::class);

        $controller->create(
            $request,
            app(GoogleSheetsService::class),
            app(GoogleCalendarService::class)
        );

        $viewing = Viewing::where('email', 'john@example.com')->latest()->first();
        $this->assertNotNull($viewing);
        $this->assertSame('cal-event-abc123', $viewing->google_calendar_id);
    }
}
