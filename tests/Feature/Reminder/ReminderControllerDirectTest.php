<?php

namespace Tests\Feature\Reminder;

use App\Http\Controllers\ReminderController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReminderControllerDirectTest extends TestCase
{
    use DatabaseTransactions;

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
    // sendListingReminder — POST /api/v1/reminder
    // ---------------------------------------------------------------

    public function test_send_listing_reminder_returns_422_when_email_missing(): void
    {
        $request    = Request::create('/api/v1/reminder', 'POST', ['date' => '2026-03-15']);
        $controller = app(ReminderController::class);

        $response = $controller->sendListingReminder($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_send_listing_reminder_returns_422_when_date_missing(): void
    {
        $request    = Request::create('/api/v1/reminder', 'POST', ['email' => 'user@example.com']);
        $controller = app(ReminderController::class);

        $response = $controller->sendListingReminder($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_send_listing_reminder_returns_422_for_invalid_email(): void
    {
        $request = Request::create('/api/v1/reminder', 'POST', [
            'email' => 'not-an-email',
            'date'  => '2026-03-15',
        ]);
        $controller = app(ReminderController::class);

        $response = $controller->sendListingReminder($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_send_listing_reminder_creates_reminder_and_returns_200(): void
    {
        $request = Request::create('/api/v1/reminder', 'POST', [
            'email' => 'user@example.com',
            'date'  => '2026-03-15',
        ]);
        $controller = app(ReminderController::class);

        $response = $controller->sendListingReminder($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertDatabaseHas('email_reminders', [
            'email'          => 'user@example.com',
            'scheduled_date' => '2026-03-13', // 2 days before 2026-03-15
        ]);
    }

    public function test_send_listing_reminder_schedules_two_days_before_provided_date(): void
    {
        $request = Request::create('/api/v1/reminder', 'POST', [
            'email' => 'user2@example.com',
            'date'  => '2026-04-01',
        ]);
        $controller = app(ReminderController::class);

        $response = $controller->sendListingReminder($request);

        $this->assertJsonStatus($response, 200);
        $this->assertDatabaseHas('email_reminders', [
            'email'          => 'user2@example.com',
            'scheduled_date' => '2026-03-30',
        ]);
    }

    public function test_send_listing_reminder_accepts_metadata_as_array(): void
    {
        $request = Request::create('/api/v1/reminder', 'POST', [
            'email'    => 'meta@example.com',
            'date'     => '2026-03-20',
            'metadata' => ['property_id' => 42, 'title' => 'Nice apartment'],
        ]);
        $controller = app(ReminderController::class);

        $response = $controller->sendListingReminder($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertEquals(
            ['property_id' => 42, 'title' => 'Nice apartment'],
            $payload['data']['metadata']
        );
    }

    public function test_send_listing_reminder_accepts_metadata_as_json_string(): void
    {
        $request = Request::create('/api/v1/reminder', 'POST', [
            'email'    => 'jsonmeta@example.com',
            'date'     => '2026-03-20',
            'metadata' => '{"property_id":99}',
        ]);
        $controller = app(ReminderController::class);

        $response = $controller->sendListingReminder($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(['property_id' => 99], $payload['data']['metadata']);
    }
}
