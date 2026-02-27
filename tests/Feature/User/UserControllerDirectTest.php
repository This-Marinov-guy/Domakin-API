<?php

namespace Tests\Feature\User;

use App\Http\Controllers\UserController;
use App\Models\User;
use App\Models\UserSettings;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserControllerDirectTest extends TestCase
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

    private function createTestUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'          => 'Test User',
            'email'         => 'test-' . Str::random(8) . '@example.com',
            'phone'         => '+316' . mt_rand(10000000, 99999999),
            'referral_code' => 'ref-' . Str::random(8),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // updateFcmToken — PATCH /api/v1/user/fcm-token
    // ---------------------------------------------------------------

    public function test_update_fcm_token_returns_404_when_user_not_found(): void
    {
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('getUserByRequest')->andReturn(null)
        );

        $request    = Request::create('/api/v1/user/fcm-token', 'PATCH', ['token' => 'test-token']);
        $controller = app(UserController::class);

        $response = $controller->updateFcmToken($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 404);
        $this->assertFalse($payload['status']);
    }

    public function test_update_fcm_token_returns_403_when_no_level1_access(): void
    {
        $user = $this->createTestUser();

        $this->mock(UserService::class, function ($mock) use ($user) {
            $mock->shouldReceive('getUserByRequest')->andReturn($user);
            $mock->shouldReceive('hasLevel1Access')->andReturn(false);
        });

        $request    = Request::create('/api/v1/user/fcm-token', 'PATCH', ['token' => 'test-token']);
        $controller = app(UserController::class);

        $response = $controller->updateFcmToken($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 403);
        $this->assertFalse($payload['status']);
    }

    public function test_update_fcm_token_throws_validation_exception_when_token_missing(): void
    {
        $user = $this->createTestUser();

        $this->mock(UserService::class, function ($mock) use ($user) {
            $mock->shouldReceive('getUserByRequest')->andReturn($user);
            $mock->shouldReceive('hasLevel1Access')->andReturn(true);
        });

        $this->expectException(ValidationException::class);

        $request    = Request::create('/api/v1/user/fcm-token', 'PATCH');
        $controller = app(UserController::class);
        $controller->updateFcmToken($request, app(UserService::class));
    }

    public function test_update_fcm_token_returns_200_on_success(): void
    {
        $user = $this->createTestUser();

        $this->mock(UserService::class, function ($mock) use ($user) {
            $mock->shouldReceive('getUserByRequest')->andReturn($user);
            $mock->shouldReceive('hasLevel1Access')->andReturn(true);
            $mock->shouldReceive('updateFcmToken')->once()->andReturnNull();
        });

        $request    = Request::create('/api/v1/user/fcm-token', 'PATCH', ['token' => 'device-token-abc123']);
        $controller = app(UserController::class);

        $response = $controller->updateFcmToken($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
    }

    // ---------------------------------------------------------------
    // updateReferralCode — PATCH /api/v1/user/referral-code
    // ---------------------------------------------------------------

    public function test_update_referral_code_returns_404_when_user_not_found(): void
    {
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('getUserByRequest')->andReturn(null)
        );

        $request    = Request::create('/api/v1/user/referral-code', 'PATCH', ['referralCode' => 'NEWCODE']);
        $controller = app(UserController::class);

        $response = $controller->updateReferralCode($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 404);
        $this->assertFalse($payload['status']);
    }

    public function test_update_referral_code_returns_422_when_code_missing(): void
    {
        $user = $this->createTestUser();

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('getUserByRequest')->andReturn($user)
        );

        $request    = Request::create('/api/v1/user/referral-code', 'PATCH', []);
        $controller = app(UserController::class);

        $response = $controller->updateReferralCode($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_update_referral_code_returns_422_when_code_too_short(): void
    {
        $user = $this->createTestUser();

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('getUserByRequest')->andReturn($user)
        );

        $request    = Request::create('/api/v1/user/referral-code', 'PATCH', ['referralCode' => 'AB']);
        $controller = app(UserController::class);

        $response = $controller->updateReferralCode($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_update_referral_code_returns_422_when_code_already_taken(): void
    {
        $this->createTestUser(['referral_code' => 'taken-ref-code']);
        $currentUser = $this->createTestUser();

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('getUserByRequest')->andReturn($currentUser)
        );

        $request    = Request::create('/api/v1/user/referral-code', 'PATCH', ['referralCode' => 'taken-ref-code']);
        $controller = app(UserController::class);

        $response = $controller->updateReferralCode($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_update_referral_code_returns_200_on_success(): void
    {
        $user = $this->createTestUser();

        $this->mock(UserService::class, function ($mock) use ($user) {
            $mock->shouldReceive('getUserByRequest')->andReturn($user);
            $mock->shouldReceive('updateReferralCode')
                ->once()
                ->andReturnUsing(function (User $u, string $code) {
                    $u->referral_code = $code;
                });
        });

        $request    = Request::create('/api/v1/user/referral-code', 'PATCH', ['referralCode' => 'MYBRAND2026']);
        $controller = app(UserController::class);

        $response = $controller->updateReferralCode($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('MYBRAND2026', $payload['data']['referral_code']);
    }

    // ---------------------------------------------------------------
    // updateNotificationSettings — PATCH /api/v1/user/notification-settings
    // ---------------------------------------------------------------

    public function test_update_notification_settings_returns_401_when_unauthenticated(): void
    {
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn(null)
        );

        $request    = Request::create('/api/v1/user/notification-settings', 'PATCH');
        $controller = app(UserController::class);

        $response = $controller->updateNotificationSettings($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 401);
        $this->assertFalse($payload['status']);
    }

    public function test_update_notification_settings_creates_new_settings_and_returns_200(): void
    {
        $user = $this->createTestUser();

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn($user->id)
        );

        $request = Request::create('/api/v1/user/notification-settings', 'PATCH', [
            'email_notifications' => false,
            'push_notifications'  => true,
        ]);
        $controller = app(UserController::class);

        $response = $controller->updateNotificationSettings($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertFalse($payload['data']['email_notifications']);
        $this->assertTrue($payload['data']['push_notifications']);
    }

    public function test_update_notification_settings_updates_existing_settings_and_returns_200(): void
    {
        $user = $this->createTestUser();

        UserSettings::create([
            'user_id'             => $user->id,
            'email_notifications' => true,
            'push_notifications'  => false,
        ]);

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn($user->id)
        );

        $request = Request::create('/api/v1/user/notification-settings', 'PATCH', [
            'push_notifications' => true,
        ]);
        $controller = app(UserController::class);

        $response = $controller->updateNotificationSettings($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertTrue($payload['data']['email_notifications']); // unchanged
        $this->assertTrue($payload['data']['push_notifications']);  // updated
    }
}
