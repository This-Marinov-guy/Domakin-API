<?php

namespace Tests\Feature\Viewing;

use App\Http\Controllers\ViewingController;
use App\Jobs\SendInternalNotificationJob;
use App\Jobs\SendViewingMailerJob;
use App\Models\Viewing;
use App\Services\GoogleServices\GoogleCalendarService;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\MailerApiService;
use App\Services\UserService;
use App\Services\ViewingMailerService;
use App\Services\ViewingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

        $request = Request::create('/api/v1/viewing/list', 'GET', [
            'per_page' => 15,
            'page' => 1,
        ]);

        $controller = app(ViewingController::class);

        $response = $controller->list($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertIsArray($payload['data']['viewings']);
        $this->assertGreaterThanOrEqual(2, $payload['data']['total']);
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

    public function test_create_fails_when_note_is_missing(): void
    {
        $this->mockViewingCreateServices();

        $request = Request::create(
            '/api/v1/viewing/create',
            'POST',
            $this->without($this->viewingCreateData(), 'note')
        );

        $controller = app(ViewingController::class);

        $response = $controller->create(
            $request,
            app(GoogleSheetsService::class),
            app(GoogleCalendarService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
        $this->assertContains('note', $payload['invalid_fields']);
    }

    public function test_create_creates_viewing_with_valid_data(): void
    {
        Queue::fake();
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
        Queue::assertPushed(SendInternalNotificationJob::class, function (SendInternalNotificationJob $job) {
            return $job->templateUuid === 'viewing'
                && $job->subjectLine === 'New viewing request'
                && ($job->data['email'] ?? null) === 'john@example.com';
        });
        Queue::assertPushed(SendViewingMailerJob::class, function (SendViewingMailerJob $job) {
            return $job->locale === 'en';
        });
    }

    public function test_viewing_mailer_sends_registered_viewing_payload(): void
    {
        $viewing = $this->createViewing();

        $this->mock(MailerApiService::class, function ($mock) use ($viewing) {
            $mock->shouldReceive('post')
                ->once()
                ->with('/viewing/send-registered-viewing', \Mockery::on(function (array $payload) use ($viewing) {
                    return $payload['email'] === 'john@example.com'
                        && $payload['id'] === (string) $viewing->id
                        && $payload['language'] === 'bg'
                        && $payload['name'] === 'John Doe'
                        && $payload['city'] === 'Amsterdam'
                        && $payload['address'] === 'Herengracht 1'
                        && $payload['date'] === '01-06-2027'
                        && $payload['time'] === '14:00'
                        && str_starts_with($payload['link'], 'mailto:info@domakin.nl?subject=')
                        && str_contains(rawurldecode($payload['link']), '01-06-2027 14:00')
                        && str_contains(rawurldecode($payload['link']), 'Herengracht 1 automation');
                }))
                ->andReturn(['ok' => true]);
        });

        app(ViewingMailerService::class)->sendRegisteredViewing($viewing, 'bg');
    }

    public function test_viewing_mailer_sends_approved_viewing_payload(): void
    {
        $viewing = $this->createViewing(['locale' => 'bg']);

        $this->mock(MailerApiService::class, function ($mock) use ($viewing) {
            $mock->shouldReceive('post')
                ->once()
                ->with('/viewing/send-approved-viewing', \Mockery::on(function (array $payload) use ($viewing) {
                    return $payload['email'] === 'john@example.com'
                        && $payload['id'] === (string) $viewing->id
                        && $payload['language'] === 'bg'
                        && $payload['name'] === 'John Doe'
                        && $payload['city'] === 'Amsterdam'
                        && $payload['address'] === 'Herengracht 1'
                        && $payload['date'] === '01-06-2027'
                        && $payload['time'] === '14:00';
                }))
                ->andReturn(['ok' => true]);
        });

        app(ViewingMailerService::class)->sendApprovedViewing($viewing);
    }

    public function test_viewing_mailer_sends_rejected_viewing_payload_with_reason(): void
    {
        $viewing = $this->createViewing();

        $this->mock(MailerApiService::class, function ($mock) use ($viewing) {
            $mock->shouldReceive('post')
                ->once()
                ->with('/viewing/send-rejected-viewing', \Mockery::on(function (array $payload) use ($viewing) {
                    return $payload['email'] === 'john@example.com'
                        && $payload['id'] === (string) $viewing->id
                        && $payload['language'] === 'en'
                        && $payload['reason'] === 'Room is no longer available.';
                }))
                ->andReturn(['ok' => true]);
        });

        app(ViewingMailerService::class)->sendRejectedViewing($viewing, 'Room is no longer available.');
    }

    public function test_viewing_approved_email_endpoint_supports_direct_email_payload(): void
    {
        $this->mock(MailerApiService::class, function ($mock) {
            $mock->shouldReceive('post')
                ->once()
                ->with('/viewing/send-approved-viewing', \Mockery::on(fn (array $payload) =>
                    $payload['email'] === 'info@domaki.nl'
                    && $payload['id'] === ''
                    && $payload['language'] === 'en'
                ))
                ->andReturn(['ok' => true]);
        });

        $request = Request::create('/api/v1/viewing/send-approved-email', 'POST', [
            'email' => 'info@domaki.nl',
            'language' => 'en',
        ]);

        $response = app(ViewingController::class)->sendApprovedEmail($request, app(ViewingMailerService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
    }

    public function test_viewing_rejected_email_endpoint_supports_direct_email_payload_with_reason(): void
    {
        $this->mock(MailerApiService::class, function ($mock) {
            $mock->shouldReceive('post')
                ->once()
                ->with('/viewing/send-rejected-viewing', \Mockery::on(fn (array $payload) =>
                    $payload['email'] === 'info@domaki.nl'
                    && $payload['language'] === 'bg'
                    && $payload['reason'] === 'Test reason'
                ))
                ->andReturn(['ok' => true]);
        });

        $request = Request::create('/api/v1/viewing/send-rejected-email', 'POST', [
            'email' => 'info@domaki.nl',
            'language' => 'bg',
            'reason' => 'Test reason',
        ]);

        $response = app(ViewingController::class)->sendRejectedEmail($request, app(ViewingMailerService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
    }

    public function test_create_attaches_google_calendar_id_to_viewing(): void
    {
        $this->mockGoogleSheetsService();
        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')
                ->once()
                ->withArgs(function ($date, $time, $description) {
                    return str_contains($description, 'Questions: Please ask if registration is possible.')
                        && !str_contains($description, 'Note:');
                })
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

    public function test_edit_fails_with_missing_id(): void
    {
        $this->mockUserService();

        $request = Request::create('/api/v1/viewing/edit', 'PATCH', [
            'status' => 2,
        ]);

        $controller = app(ViewingController::class);

        $response = $controller->edit(
            $request,
            app(ViewingService::class),
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_edit_updates_viewing_with_valid_data(): void
    {
        $this->mockUserService();
        $viewing = $this->createViewing();

        $request = Request::create(
            '/api/v1/viewing/edit',
            'PATCH',
            $this->viewingEditData($viewing->id)
        );

        $controller = app(ViewingController::class);

        $response = $controller->edit(
            $request,
            app(ViewingService::class),
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(2, $payload['data']['status']);
        $this->assertSame('Confirmed by admin', $payload['data']['internal_note']);
    }

    public function test_edit_ignores_malformed_internal_updater_id(): void
    {
        $this->mockUserService('0');
        $viewing = $this->createViewing();

        $request = Request::create(
            '/api/v1/viewing/edit',
            'PATCH',
            $this->viewingEditData($viewing->id)
        );

        $controller = app(ViewingController::class);

        $response = $controller->edit(
            $request,
            app(ViewingService::class),
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(2, $payload['data']['status']);
        $this->assertSame('Confirmed by admin', $payload['data']['internal_note']);
        $this->assertNull($payload['data']['internal_updated_by_user']);
    }
}
