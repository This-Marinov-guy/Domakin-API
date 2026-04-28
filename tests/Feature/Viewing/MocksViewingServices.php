<?php

namespace Tests\Feature\Viewing;

use App\Services\GoogleServices\GoogleCalendarService;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\UserService;

trait MocksViewingServices
{
    protected function mockUserService(?string $userId = null): void
    {
        $this->mock(UserService::class, function ($mock) use ($userId) {
            $mock->shouldReceive('extractIdFromRequest')->andReturn($userId)->byDefault();
        });
    }

    protected function mockGoogleSheetsService(): void
    {
        $this->mock(GoogleSheetsService::class, function ($mock) {
            $mock->shouldReceive('exportModelToSpreadsheet')->andReturn(null)->byDefault();
            $mock->shouldReceive('updateFirstEmptyIdRow')->andReturn(true)->byDefault();
        });
    }

    protected function mockGoogleCalendarService(): void
    {
        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')
                ->andReturn('mock-calendar-event-id')
                ->byDefault();
        });
    }

    /** Mock all external services needed for ViewingController::create */
    protected function mockViewingCreateServices(): void
    {
        $this->mockGoogleSheetsService();
        $this->mockGoogleCalendarService();
    }
}
