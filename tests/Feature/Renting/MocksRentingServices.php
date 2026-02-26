<?php

namespace Tests\Feature\Renting;

use App\Files\CloudinaryService;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\UserService;

trait MocksRentingServices
{
    public const TEST_USER_UUID = '00000000-0000-0000-0000-000000000001';

    protected function mockCloudinaryService(): void
    {
        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('singleUpload')
                ->andReturn('https://mocked.cloudinary.com/letter.pdf')
                ->byDefault();
        });
    }

    protected function mockGoogleSheetsService(): void
    {
        $this->mock(GoogleSheetsService::class, function ($mock) {
            $mock->shouldReceive('exportModelToSpreadsheet')->andReturn(null)->byDefault();
            $mock->shouldReceive('updateFirstEmptyIdRow')->andReturn(true)->byDefault();
        });
    }

    protected function mockUserService(?string $userId = null): void
    {
        $this->mock(UserService::class, function ($mock) use ($userId) {
            // Return null so internal_updated_by stays null (avoids FK violation against users)
            $mock->shouldReceive('extractIdFromRequest')->andReturn($userId)->byDefault();
        });
    }

    /** Mock everything external for RentingController::create */
    protected function mockRentingCreateServices(): void
    {
        $this->mockCloudinaryService();
        $this->mockGoogleSheetsService();
    }
}
