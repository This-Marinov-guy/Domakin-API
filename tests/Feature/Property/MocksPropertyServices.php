<?php

namespace Tests\Feature\Property;

use App\Files\CloudinaryService;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\Integrations\SignalIntegrationService;
use App\Services\ListingMailerService;
use App\Services\Payment\PaymentLinkService;
use App\Services\UserService;

trait MocksPropertyServices
{
    // UUID used as a fake owner across tests that need a user ID in a UUID column.
    public const TEST_USER_UUID = '00000000-0000-0000-0000-000000000001';

    protected function mockUserService(string $userId = self::TEST_USER_UUID): void
    {
        $this->mock(UserService::class, function ($mock) use ($userId) {
            $mock->shouldReceive('extractIdFromRequest')->andReturn($userId)->byDefault();
        });
    }

    protected function mockCloudinaryService(): void
    {
        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('multiUpload')
                ->andReturn(['https://mocked.cloudinary.com/test.jpg'])
                ->byDefault();
        });
    }

    protected function mockPaymentLinkService(): void
    {
        $this->mock(PaymentLinkService::class, function ($mock) {
            $mock->shouldReceive('createPropertyFeeLink')->andReturn(null)->byDefault();
        });
    }

    protected function mockGoogleSheetsService(): void
    {
        $this->mock(GoogleSheetsService::class, function ($mock) {
            $mock->shouldReceive('exportModelToSpreadsheet')->andReturn(null)->byDefault();
        });
    }

    protected function mockListingMailerService(): void
    {
        $this->mock(ListingMailerService::class, function ($mock) {
            $mock->shouldReceive('sendSubmittedListing')->andReturn(null)->byDefault();
        });
    }

    protected function mockSignalIntegrationService(): void
    {
        $this->mock(SignalIntegrationService::class, function ($mock) {
            $mock->shouldReceive('submitProperty')->andReturn(null)->byDefault();
            $mock->shouldReceive('deleteProperty')->andReturn(null)->byDefault();
        });
    }

    /** Mock everything needed for PropertyController::create */
    protected function mockCreateServices(): void
    {
        $this->mockUserService();
        $this->mockCloudinaryService();
        $this->mockPaymentLinkService();
        $this->mockGoogleSheetsService();
        $this->mockListingMailerService();
    }

    /** Mock everything needed for PropertyController::edit */
    protected function mockEditServices(): void
    {
        $this->mockUserService();
        $this->mockPaymentLinkService();
        $this->mockSignalIntegrationService();
    }
}
