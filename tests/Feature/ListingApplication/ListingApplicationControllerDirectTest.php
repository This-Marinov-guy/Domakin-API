<?php

namespace Tests\Feature\ListingApplication;

use App\Http\Controllers\ListingApplicationController;
use App\Jobs\ReformatPropertyDescriptionJob;
use App\Models\ListingApplication;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\ListingApplicationService;
use App\Services\ListingMailerService;
use App\Services\Payment\PaymentLinkService;
use App\Services\PropertyService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ListingApplicationControllerDirectTest extends TestCase
{
    use DatabaseTransactions;
    use MocksListingServices;
    use ListingApplicationData;

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
    // validateStep2 — POST /api/v1/listing-application/validate/step-2
    // ---------------------------------------------------------------

    public function test_validate_step2_direct_creates_application_with_valid_data(): void
    {
        $request = Request::create(
            '/api/v1/listing-application/validate/step-2',
            'POST',
            $this->step2Data()
        );

        /** @var ListingApplicationController $controller */
        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep2(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('vlady1002@abv.bg', $payload['data']['email']);
        $this->assertSame(3, $payload['data']['step']);
        $this->assertArrayHasKey('referenceId', $payload['data']);
    }

    public function test_validate_step2_direct_fails_with_missing_required_fields(): void
    {
        $request = Request::create(
            '/api/v1/listing-application/validate/step-2',
            'POST',
            $this->without($this->step2Data(), 'surname', 'email', 'phone')
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep2(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // validateStep3 — POST /api/v1/listing-application/validate/step-3
    // ---------------------------------------------------------------

    public function test_validate_step3_direct_updates_application_with_valid_data(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForStep3())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/validate/step-3',
            'POST',
            $this->step3Data($application->reference_id)
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep3(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(4, $payload['data']['step']);
    }

    public function test_validate_step3_direct_fails_missing_address(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForStep3())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/validate/step-3',
            'POST',
            $this->without($this->step3Data($application->reference_id), 'address')
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep3(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // validateStep4 — POST /api/v1/listing-application/validate/step-4
    // ---------------------------------------------------------------

    public function test_validate_step4_direct_updates_application_with_valid_data(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForStep4())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/validate/step-4',
            'POST',
            $this->step4Data($application->reference_id)
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep4(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(5, $payload['data']['step']);
    }

    public function test_validate_step4_direct_fails_missing_rent(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForStep4())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/validate/step-4',
            'POST',
            $this->without($this->step4Data($application->reference_id), 'rent')
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep4(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // validateStep5 — POST /api/v1/listing-application/validate/step-5
    // ---------------------------------------------------------------

    public function test_validate_step5_direct_succeeds_with_existing_images_string(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForStep5WithImages())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/validate/step-5',
            'POST',
            $this->step5Data($application->reference_id)
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep5(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(6, $payload['data']['step']);
    }

    public function test_validate_step5_direct_fails_without_images(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForStep5())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/validate/step-5',
            'POST',
            $this->without($this->step5Data($application->reference_id), 'images')
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->validateStep5(
            $request,
            app(ListingApplicationService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // save — POST /api/v1/listing-application/save
    // ---------------------------------------------------------------

    public function test_save_direct_creates_new_application(): void
    {
        $this->mockMailerService();

        $request = Request::create(
            '/api/v1/listing-application/save',
            'POST',
            $this->saveData()
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->save(
            $request,
            app(ListingApplicationService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('vlady1002@abv.bg', $payload['data']['email']);
        $this->assertArrayHasKey('referenceId', $payload['data']);
    }

    public function test_save_direct_returns_error_for_unknown_reference_id(): void
    {
        $this->mockMailerService();

        $request = Request::create(
            '/api/v1/listing-application/save',
            'POST',
            $this->saveData(['referenceId' => '00000000-0000-0000-0000-000000000000'])
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->save(
            $request,
            app(ListingApplicationService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // show — GET /api/v1/listing-application/{referenceId}
    // ---------------------------------------------------------------

    public function test_show_direct_returns_application_by_reference_id(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForShow())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/' . $application->reference_id,
            'GET'
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->show(
            $request,
            $application->reference_id,
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('example@example.com', $payload['data']['email']);
    }

    public function test_show_direct_returns_error_for_unknown_reference_id(): void
    {
        $request = Request::create(
            '/api/v1/listing-application/00000000-0000-0000-0000-000000000000',
            'GET'
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->show(
            $request,
            '00000000-0000-0000-0000-000000000000',
            app(UserService::class)
        );

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // submit — POST /api/v1/listing-application/submit
    // ---------------------------------------------------------------

    public function test_submit_direct_request_missing_reference_id_returns_422(): void
    {
        $request = Request::create(
            '/api/v1/listing-application/submit',
            'POST',
            $this->submitDataEmpty()
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->submit(
            $request,
            app(UserService::class),
            app(PropertyService::class),
            app(PaymentLinkService::class),
            app(GoogleSheetsService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_submit_direct_fails_for_unknown_reference_id(): void
    {
        $request = Request::create(
            '/api/v1/listing-application/submit',
            'POST',
            $this->submitData('00000000-0000-0000-0000-000000000000')
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->submit(
            $request,
            app(UserService::class),
            app(PropertyService::class),
            app(PaymentLinkService::class),
            app(GoogleSheetsService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_submit_direct_fails_when_application_missing_size_rent_bills_deposit(): void
    {
        $application = ListingApplication::create($this->applicationAttrsForSubmitIncomplete())->fresh();

        $request = Request::create(
            '/api/v1/listing-application/submit',
            'POST',
            $this->submitData($application->reference_id)
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->submit(
            $request,
            app(UserService::class),
            app(PropertyService::class),
            app(PaymentLinkService::class),
            app(GoogleSheetsService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_submit_direct_creates_property_and_removes_application(): void
    {
        Queue::fake();

        $application = ListingApplication::create($this->applicationAttrsForSubmitSuccess())->fresh();

        $this->mockPaymentAndSheetsOnly();
        $this->mockMailerService();

        $request = Request::create(
            '/api/v1/listing-application/submit',
            'POST',
            $this->submitData($application->reference_id)
        );

        $controller = app(ListingApplicationController::class);

        $response = $controller->submit(
            $request,
            app(UserService::class),
            app(PropertyService::class),
            app(PaymentLinkService::class),
            app(GoogleSheetsService::class),
            app(ListingMailerService::class)
        );

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);

        $this->assertNull(
            ListingApplication::where('reference_id', $application->reference_id)->first()
        );

        Queue::assertPushed(ReformatPropertyDescriptionJob::class);
    }
}

