<?php

namespace Tests\Feature\Career;

use App\Files\CloudinaryService;
use App\Http\Controllers\CareerController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CareerControllerDirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        try {
            DB::reconnect();
        } catch (\Throwable) {
        }
        parent::setUp();

        // Prevent SDK constructor issues — override per-test when needed.
        $this->mock(CloudinaryService::class, fn ($m) =>
            $m->shouldReceive('singleUpload')
                ->andReturn('https://res.cloudinary.com/test/careers/cvs/resume.pdf')
                ->byDefault()
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        try {
            DB::reconnect();
        } catch (\Throwable) {
        }
    }

    private function validCareerData(array $overrides = []): array
    {
        return array_merge([
            'name'     => 'John Doe',
            'email'    => 'john.doe@example.com',
            'phone'    => '+31612345678',
            'position' => 'viewing_agent',
            'location' => 'Amsterdam, Netherlands',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // apply — POST /api/v1/career/apply
    // ---------------------------------------------------------------

    public function test_apply_returns_422_when_required_fields_missing(): void
    {
        $request = Request::create('/api/v1/career/apply', 'POST', [
            'name' => 'John Doe',
            // missing email, phone, position, location
        ]);
        $controller = app(CareerController::class);

        $response = $controller->apply($request, app(CloudinaryService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
        $this->assertArrayHasKey('invalid_fields', $payload);
    }

    public function test_apply_returns_422_for_invalid_email(): void
    {
        $request = Request::create(
            '/api/v1/career/apply',
            'POST',
            $this->validCareerData(['email' => 'not-an-email'])
        );
        $controller = app(CareerController::class);

        $response = $controller->apply($request, app(CloudinaryService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_apply_returns_422_for_phone_too_short(): void
    {
        $request = Request::create(
            '/api/v1/career/apply',
            'POST',
            $this->validCareerData(['phone' => '123'])
        );
        $controller = app(CareerController::class);

        $response = $controller->apply($request, app(CloudinaryService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_apply_returns_200_with_valid_data_and_no_resume(): void
    {
        Mail::fake();

        $request = Request::create(
            '/api/v1/career/apply',
            'POST',
            $this->validCareerData(['experience' => '3 years', 'message' => 'Motivated candidate'])
        );
        $controller = app(CareerController::class);

        $response = $controller->apply($request, app(CloudinaryService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertDatabaseHas('careers', [
            'email'    => 'john.doe@example.com',
            'position' => 'viewing_agent',
            'resume'   => null,
        ]);
    }

    public function test_apply_returns_200_with_valid_data_and_resume(): void
    {
        Mail::fake();

        $request = Request::create('/api/v1/career/apply', 'POST', $this->validCareerData());
        $request->files->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'));

        $controller = app(CareerController::class);

        $response = $controller->apply($request, app(CloudinaryService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertDatabaseHas('careers', [
            'email'  => 'john.doe@example.com',
            'resume' => 'https://res.cloudinary.com/test/careers/cvs/resume.pdf',
        ]);
    }

    public function test_apply_returns_400_when_resume_upload_fails(): void
    {
        $this->mock(CloudinaryService::class, fn ($m) =>
            $m->shouldReceive('singleUpload')
                ->andThrow(new \Exception('Cloudinary connection failed'))
        );

        $request = Request::create('/api/v1/career/apply', 'POST', $this->validCareerData());
        $request->files->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'));

        $controller = app(CareerController::class);

        $response = $controller->apply($request, app(CloudinaryService::class));

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }
}
