<?php

namespace Tests\Feature\ListingApplication;

use App\Jobs\ReformatPropertyDescriptionJob;
use App\Models\ListingApplication;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ListingApplicationTest extends TestCase
{
    use DatabaseTransactions;
    use MocksListingServices;
    use ListingApplicationData;

    private const JWT_ALGO = 'HS256';

    private const TEST_USER_ID    = '9c99f1c5-4185-4911-9405-44ce8af10e53';
    private const TEST_USER_EMAIL = 'vlady1002@abv.bg';
    private const TEST_USER_NAME  = 'Vladislav Admin';

    private User $testUser;

    // ---------------------------------------------------------------
    // Boot: load .env.dev database + supabase config
    // ---------------------------------------------------------------

    protected function getEnvironmentSetUp($app): void
    {
        $env = self::parseEnvFile(base_path('.env.dev'));

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver'   => 'pgsql',
            'host'     => $env['DB_HOST']     ?? '127.0.0.1',
            'port'     => (int) ($env['DB_PORT'] ?? 5432),
            'database' => trim($env['DB_DATABASE'] ?? 'postgres'),
            'username' => trim($env['DB_USERNAME'] ?? 'postgres'),
            'password' => $env['DB_PASSWORD']  ?? '',
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
        ]);

        $app['config']->set('supabase.jwt_secret', $env['SUPABASE_JWT_SECRET'] ?? '');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $user = User::find(self::TEST_USER_ID);

        if (!$user) {
            $this->markTestSkipped(
                'Test user ' . self::TEST_USER_EMAIL . ' (id: ' . self::TEST_USER_ID . ') not found in the database. ' .
                'Create the user manually or update TEST_USER_ID to match an existing user.'
            );
        }

        $this->testUser = $user;

        $this->mockMailerService();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private static function parseEnvFile(string $path): array
    {
        $env = [];
        if (!file_exists($path)) {
            return $env;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
                $value = trim($value, '"\'');
            } elseif (($pos = strpos($value, ' #')) !== false) {
                $value = trim(substr($value, 0, $pos));
            }
            $env[trim($key)] = $value;
        }
        return $env;
    }

    private function makeJwt(string $role = ''): string
    {
        $payload = [
            'sub' => (string) $this->testUser->id,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        if ($role === 'admin') {
            $payload['app_metadata'] = (object) ['role' => 'admin'];
        }

        return JWT::encode($payload, config('supabase.jwt_secret'), self::JWT_ALGO);
    }

    private function makeApplication(array $overrides = []): ListingApplication
    {
        return ListingApplication::create(array_merge([
            'name'            => 'Vladislav',
            'surname'         => 'Admin',
            'email'           => self::TEST_USER_EMAIL,
            'phone'           => '+31612345678',
            'step'            => 3,
            'registration'    => true,
            'pets_allowed'    => false,
            'smoking_allowed' => false,
            'furnished_type'  => 1,
            'bathrooms'       => 1,
            'toilets'         => 1,
            'flatmates'       => ['0', '0'],
            'user_id'         => (string) $this->testUser->id,
        ], $overrides));
    }

    private function makeFullApplication(array $overrides = []): ListingApplication
    {
        return $this->makeApplication(array_merge([
            'type'           => 1,
            'city'           => 'Amsterdam',
            'address'        => 'Herengracht 1',
            'postcode'       => '1015 BZ',
            'size'           => 25,
            'rent'           => 850,
            'bills'          => 80,
            'deposit'        => 500,
            'description'    => ['en' => 'Test room available'],
            'images'         => 'https://example.com/image.jpg',
            'step'           => 5,
            'available_from' => '2026-03-01',
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // validateStep2 — POST /api/v1/listing-application/validate/step-2
    // ---------------------------------------------------------------

    public function test_validate_step2_creates_application_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/listing-application/validate/step-2', $this->step2Data());

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.email', self::TEST_USER_EMAIL)
            ->assertJsonPath('data.step', 3)
            ->assertJsonStructure(['data' => ['referenceId']]);
    }

    public function test_validate_step2_updates_existing_application_by_reference_id(): void
    {
        $application = $this->makeApplication(['name' => 'Old Name']);

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-2',
            $this->step2Data(['referenceId' => $application->reference_id, 'name' => 'Updated Name'])
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_validate_step2_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-2',
            $this->without($this->step2Data(), 'surname', 'email', 'phone')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_validate_step2_fails_with_invalid_email(): void
    {
        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-2',
            $this->step2Data(['email' => 'not-a-valid-email'])
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_validate_step2_fails_with_phone_too_short(): void
    {
        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-2',
            $this->step2Data(['phone' => '123']) // too short (min:6)
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    // ---------------------------------------------------------------
    // validateStep3 — POST /api/v1/listing-application/validate/step-3
    // ---------------------------------------------------------------

    public function test_validate_step3_updates_application_with_valid_data(): void
    {
        $application = $this->makeApplication();

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-3',
            $this->step3Data($application->reference_id)
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.step', 4);
    }

    public function test_validate_step3_fails_missing_address(): void
    {
        $application = $this->makeApplication();

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-3',
            $this->without($this->step3Data($application->reference_id), 'address')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_validate_step3_fails_missing_registration(): void
    {
        $application = $this->makeApplication();

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-3',
            $this->without($this->step3Data($application->reference_id), 'registration')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_validate_step3_fails_missing_available_from(): void
    {
        $application = $this->makeApplication();

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-3',
            $this->without($this->step3Data($application->reference_id), 'availableFrom')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    // ---------------------------------------------------------------
    // validateStep4 — POST /api/v1/listing-application/validate/step-4
    // ---------------------------------------------------------------

    public function test_validate_step4_updates_application_with_valid_data(): void
    {
        $application = $this->makeApplication(['step' => 4]);

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-4',
            $this->step4Data($application->reference_id)
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.step', 5);
    }

    public function test_validate_step4_fails_missing_rent(): void
    {
        $application = $this->makeApplication(['step' => 4]);

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-4',
            $this->without($this->step4Data($application->reference_id), 'rent')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_validate_step4_fails_rent_below_minimum(): void
    {
        $application = $this->makeApplication(['step' => 4]);

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-4',
            $this->step4Data($application->reference_id, ['rent' => 0, 'bills' => 0, 'deposit' => 0]) // below min:1
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_validate_step4_fails_missing_bathrooms(): void
    {
        $application = $this->makeApplication(['step' => 4]);

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-4',
            $this->without($this->step4Data($application->reference_id), 'bathrooms')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    // ---------------------------------------------------------------
    // validateStep5 — POST /api/v1/listing-application/validate/step-5
    // ---------------------------------------------------------------

    public function test_validate_step5_succeeds_with_existing_images_string(): void
    {
        $application = $this->makeApplication(['step' => 5, 'images' => 'https://example.com/image.jpg']);

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-5',
            $this->step5Data($application->reference_id)
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.step', 6);
    }

    public function test_validate_step5_fails_without_images(): void
    {
        $application = $this->makeApplication(['step' => 5]);

        $response = $this->postJson(
            '/api/v1/listing-application/validate/step-5',
            $this->without($this->step5Data($application->reference_id), 'images')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    // ---------------------------------------------------------------
    // save — POST /api/v1/listing-application/save
    // ---------------------------------------------------------------

    public function test_save_creates_new_application(): void
    {
        $response = $this->postJson('/api/v1/listing-application/save', $this->saveData());

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.email', self::TEST_USER_EMAIL)
            ->assertJsonStructure(['data' => ['referenceId']]);
    }

    public function test_save_updates_existing_application_by_reference_id(): void
    {
        $application = $this->makeApplication(['name' => 'Old Name']);

        $response = $this->postJson(
            '/api/v1/listing-application/save',
            $this->saveData(['referenceId' => $application->reference_id, 'name' => 'New Name'])
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_save_returns_error_for_unknown_reference_id(): void
    {
        $response = $this->postJson(
            '/api/v1/listing-application/save',
            $this->saveData(['referenceId' => '00000000-0000-0000-0000-000000000000'])
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    // ---------------------------------------------------------------
    // show — GET /api/v1/listing-application/{referenceId}
    // ---------------------------------------------------------------

    public function test_show_returns_application_by_reference_id(): void
    {
        $application = $this->makeApplication();

        $response = $this->getJson('/api/v1/listing-application/' . $application->reference_id);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.email', $application->email);
    }

    public function test_show_returns_error_for_unknown_reference_id(): void
    {
        $response = $this->getJson('/api/v1/listing-application/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    // ---------------------------------------------------------------
    // list — GET /api/v1/listing-application/list (auth.role)
    // ---------------------------------------------------------------

    public function test_list_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/v1/listing-application/list');

        $response->assertStatus(401);
    }

    public function test_list_returns_applications_for_authenticated_user(): void
    {
        $this->makeApplication(['email' => 'first@example.com']);
        $this->makeApplication(['email' => 'second@example.com']);

        $response = $this->withToken($this->makeJwt())
            ->getJson('/api/v1/listing-application/list');

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['data', 'current_page', 'last_page', 'per_page', 'total']]);

        $this->assertGreaterThanOrEqual(2, $response->json('data.total'));
    }

    public function test_list_supports_pagination_parameters(): void
    {
        $this->makeApplication();

        $response = $this->withToken($this->makeJwt())
            ->getJson('/api/v1/listing-application/list?page=1&per_page=5');

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.current_page', 1);
    }

    // ---------------------------------------------------------------
    // listAll — GET /api/v1/listing-application/list-extended (admin)
    // ---------------------------------------------------------------

    public function test_list_all_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/v1/listing-application/list-extended');

        $response->assertStatus(401);
    }

    public function test_list_all_returns_403_for_non_admin_user(): void
    {
        $response = $this->withToken($this->makeJwt())
            ->getJson('/api/v1/listing-application/list-extended');

        $response->assertStatus(403);
    }

    public function test_list_all_returns_all_applications_for_admin(): void
    {
        $this->makeApplication(['email' => 'a@example.com']);
        $this->makeApplication(['email' => 'b@example.com']);

        $response = $this->withToken($this->makeJwt('admin'))
            ->getJson('/api/v1/listing-application/list-extended');

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['data', 'current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_list_all_filters_by_search(): void
    {
        $this->makeApplication(['email' => 'uniquesearch@example.com']);

        $response = $this->withToken($this->makeJwt('admin'))
            ->getJson('/api/v1/listing-application/list-extended?search=uniquesearch');

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
    }

    public function test_list_all_filters_by_reference_id(): void
    {
        $application = $this->makeApplication();

        $response = $this->withToken($this->makeJwt('admin'))
            ->getJson('/api/v1/listing-application/list-extended?referenceId=' . $application->reference_id);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.total', 1);
    }

    // ---------------------------------------------------------------
    // edit — PATCH /api/v1/listing-application/edit (auth.role)
    // ---------------------------------------------------------------

    public function test_edit_returns_401_without_token(): void
    {
        $application = $this->makeApplication();

        $response = $this->patchJson(
            '/api/v1/listing-application/edit',
            $this->editData($application->id, ['name' => 'Changed'])
        );

        $response->assertStatus(401);
    }

    public function test_edit_updates_application_for_owner(): void
    {
        $application = $this->makeApplication();

        $response = $this->withToken($this->makeJwt())
            ->patchJson(
                '/api/v1/listing-application/edit',
                $this->editData($application->id, ['name' => 'Changed Name'])
            );

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.name', 'Changed Name');
    }

    public function test_edit_returns_error_when_application_not_owned_by_user(): void
    {
        $application = $this->makeApplication(['user_id' => null]);

        $response = $this->withToken($this->makeJwt())
            ->patchJson(
                '/api/v1/listing-application/edit',
                $this->editData($application->id, ['name' => 'Changed'])
            );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_edit_normalises_camel_case_keys(): void
    {
        $application = $this->makeApplication();

        $response = $this->withToken($this->makeJwt())
            ->patchJson(
                '/api/v1/listing-application/edit',
                $this->editData($application->id, ['furnishedType' => 2, 'petsAllowed' => true])
            );

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.furnished_type', 2);
    }

    // ---------------------------------------------------------------
    // destroy — DELETE /api/v1/listing-application/delete (auth.role)
    // ---------------------------------------------------------------

    public function test_destroy_returns_401_without_token(): void
    {
        $application = $this->makeApplication();

        $response = $this->deleteJson(
            '/api/v1/listing-application/delete',
            $this->deleteData($application->id)
        );

        $response->assertStatus(401);
    }

    public function test_destroy_deletes_application_for_owner(): void
    {
        $application = $this->makeApplication();

        $response = $this->withToken($this->makeJwt())
            ->deleteJson(
                '/api/v1/listing-application/delete',
                $this->deleteData($application->id)
            );

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertNull(ListingApplication::find($application->id));
    }

    public function test_destroy_returns_error_when_application_not_owned_by_user(): void
    {
        $application = $this->makeApplication(['user_id' => null]);

        $response = $this->withToken($this->makeJwt())
            ->deleteJson(
                '/api/v1/listing-application/delete',
                $this->deleteData($application->id)
            );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    // ---------------------------------------------------------------
    // submit — POST /api/v1/listing-application/submit
    // ---------------------------------------------------------------

    public function test_submit_fails_without_reference_id(): void
    {
        $response = $this->postJson('/api/v1/listing-application/submit', []);

        $response->assertStatus(422)
            ->assertJson(['status' => false])
            ->assertJsonStructure(['invalid_fields']);
    }

    public function test_submit_fails_for_unknown_reference_id(): void
    {
        $response = $this->postJson(
            '/api/v1/listing-application/submit',
            $this->submitData('00000000-0000-0000-0000-000000000000')
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_submit_fails_when_application_missing_size_rent_bills_deposit(): void
    {
        $application = $this->makeApplication(['step' => 3]);

        $response = $this->postJson(
            '/api/v1/listing-application/submit',
            $this->submitData($application->reference_id)
        );

        $response->assertStatus(200)
            ->assertJson(['status' => false]);
    }

    public function test_submit_creates_property_and_removes_application(): void
    {
        $application = $this->makeFullApplication();

        $this->mockSubmitServices();

        $response = $this->postJson(
            '/api/v1/listing-application/submit',
            $this->submitData($application->reference_id)
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertNull(
            ListingApplication::where('reference_id', $application->reference_id)->first()
        );

        Queue::assertPushed(ReformatPropertyDescriptionJob::class);
    }
}
