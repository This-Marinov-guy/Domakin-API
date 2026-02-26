<?php

namespace Tests\Feature\ListingApplication;

use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\ListingMailerService;
use App\Services\Payment\PaymentLinkService;
use App\Services\PropertyService;

trait MocksListingServices
{
    protected function mockMailerService(): void
    {
        $this->mock(ListingMailerService::class, function ($mock) {
            $mock->shouldReceive('sendFinishApplication')->andReturn(null)->byDefault();
            $mock->shouldReceive('sendSubmittedListing')->andReturn(null)->byDefault();
        });
    }

    protected function mockSubmitServices(): void
    {
        $this->mock(
            PropertyService::class,
            fn($m) => $m->shouldReceive('modifyPropertyDataWithTranslations')
                ->andReturnUsing(fn($data) => $data)
        );
        $this->mock(
            PaymentLinkService::class,
            fn($m) => $m->shouldReceive('createPropertyFeeLink')->andReturn(null)
        );
        $this->mock(
            GoogleSheetsService::class,
            fn($m) => $m->shouldReceive('exportModelToSpreadsheet')->andReturn(null)
        );
    }

    /**
     * Mock only payment and sheets for submit; leave PropertyService real so
     * modifyPropertyDataWithTranslations encodes title/period/flatmates/description for DB.
     */
    protected function mockPaymentAndSheetsOnly(): void
    {
        $this->mock(
            PaymentLinkService::class,
            fn($m) => $m->shouldReceive('createPropertyFeeLink')->andReturn(null)
        );
        $this->mock(
            GoogleSheetsService::class,
            fn($m) => $m->shouldReceive('exportModelToSpreadsheet')->andReturn(null)
        );
    }
}
