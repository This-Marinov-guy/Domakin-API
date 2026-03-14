<?php

namespace Tests\Feature\ReferralBonus;

use App\Models\ReferralBonus;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HTTP-layer tests that exercise the auth.role:admin middleware on referral-bonus routes
 * and the auth.role middleware on the my-list endpoint.
 */
class ReferralBonusHttpTest extends TestCase
{
    use DatabaseTransactions;

    private const JWT_ALGO = 'HS256';

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

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'          => 'Test User',
            'email'         => 'test-' . Str::random(8) . '@example.com',
            'phone'         => '+316' . mt_rand(10000000, 99999999),
            'referral_code' => 'REF-' . Str::random(6),
        ], $overrides));
    }

    private function makeJwt(string $userId, bool $admin = false): string
    {
        $payload = [
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        if ($admin) {
            $payload['app_metadata'] = (object) ['role' => 'admin'];
        }

        return JWT::encode($payload, config('supabase.jwt_secret'), self::JWT_ALGO);
    }

    private function createBonus(array $overrides = []): ReferralBonus
    {
        return ReferralBonus::create(array_merge([
            'referral_code' => 'HTTP-TEST',
            'amount'        => 100,
            'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
            'type'          => ReferralBonus::TYPE_LISTING,
            'reference_id'  => (string) mt_rand(10000, 99999),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Admin routes — unauthenticated (401)
    // ---------------------------------------------------------------

    public function test_list_returns_401_without_token(): void
    {
        $this->getJson('/api/v1/referral-bonus/list')
            ->assertStatus(401);
    }

    public function test_show_returns_401_without_token(): void
    {
        $bonus = $this->createBonus();
        $this->getJson('/api/v1/referral-bonus/' . $bonus->id)
            ->assertStatus(401);
    }

    public function test_create_returns_401_without_token(): void
    {
        $this->postJson('/api/v1/referral-bonus/create', [])
            ->assertStatus(401);
    }

    public function test_edit_returns_401_without_token(): void
    {
        $this->patchJson('/api/v1/referral-bonus/edit', [])
            ->assertStatus(401);
    }

    public function test_delete_returns_401_without_token(): void
    {
        $this->deleteJson('/api/v1/referral-bonus/delete')
            ->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Admin routes — non-admin user (403)
    // ---------------------------------------------------------------

    public function test_list_returns_403_for_non_admin(): void
    {
        $user = $this->createUser();

        $this->withToken($this->makeJwt($user->id))
            ->getJson('/api/v1/referral-bonus/list')
            ->assertStatus(403);
    }

    public function test_create_returns_403_for_non_admin(): void
    {
        $user = $this->createUser();

        $this->withToken($this->makeJwt($user->id))
            ->postJson('/api/v1/referral-bonus/create', [
                'referral_code' => 'CODE',
                'type'          => 1,
                'reference_id'  => '1',
            ])
            ->assertStatus(403);
    }

    public function test_edit_returns_403_for_non_admin(): void
    {
        $user  = $this->createUser();
        $bonus = $this->createBonus();

        $this->withToken($this->makeJwt($user->id))
            ->patchJson('/api/v1/referral-bonus/edit', ['id' => $bonus->id, 'status' => 2])
            ->assertStatus(403);
    }

    public function test_delete_returns_403_for_non_admin(): void
    {
        $user  = $this->createUser();
        $bonus = $this->createBonus();

        $this->withToken($this->makeJwt($user->id))
            ->deleteJson('/api/v1/referral-bonus/delete', ['id' => $bonus->id])
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Admin routes — admin user (200)
    // ---------------------------------------------------------------

    public function test_list_returns_200_for_admin(): void
    {
        $user = $this->createUser();

        $this->withToken($this->makeJwt($user->id, admin: true))
            ->getJson('/api/v1/referral-bonus/list')
            ->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_show_returns_200_for_admin(): void
    {
        $user  = $this->createUser();
        $bonus = $this->createBonus();

        $this->withToken($this->makeJwt($user->id, admin: true))
            ->getJson('/api/v1/referral-bonus/' . $bonus->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $bonus->id);
    }

    public function test_create_returns_200_for_admin(): void
    {
        $user = $this->createUser();

        $this->withToken($this->makeJwt($user->id, admin: true))
            ->postJson('/api/v1/referral-bonus/create', [
                'referral_code' => 'ADMINCODE',
                'type'          => ReferralBonus::TYPE_LISTING,
                'reference_id'  => '9001',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.referral_code', 'ADMINCODE');
    }

    public function test_edit_returns_200_for_admin(): void
    {
        $user  = $this->createUser();
        $bonus = $this->createBonus();

        $this->withToken($this->makeJwt($user->id, admin: true))
            ->patchJson('/api/v1/referral-bonus/edit', [
                'id'     => $bonus->id,
                'status' => ReferralBonus::STATUS_COMPLETED,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReferralBonus::STATUS_COMPLETED);
    }

    public function test_delete_returns_200_for_admin(): void
    {
        $user  = $this->createUser();
        $bonus = $this->createBonus();

        $this->withToken($this->makeJwt($user->id, admin: true))
            ->deleteJson('/api/v1/referral-bonus/delete', ['id' => $bonus->id])
            ->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertNull(ReferralBonus::find($bonus->id));
    }

    // ---------------------------------------------------------------
    // my-list — auth.role (any authenticated user)
    // ---------------------------------------------------------------

    public function test_my_list_returns_401_without_token(): void
    {
        $this->getJson('/api/v1/referral-bonus/my-list')
            ->assertStatus(401);
    }

    public function test_my_list_returns_200_for_authenticated_user(): void
    {
        $user = $this->createUser();
        $this->createBonus(['user_id' => $user->id, 'reference_id' => '9100']);
        $this->createBonus(['user_id' => $user->id, 'reference_id' => '9101']);

        $this->withToken($this->makeJwt($user->id))
            ->getJson('/api/v1/referral-bonus/my-list')
            ->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.total', 2);
    }

    public function test_my_list_returns_200_for_admin_user(): void
    {
        $user = $this->createUser();
        $this->createBonus(['user_id' => $user->id, 'reference_id' => '9200']);

        $this->withToken($this->makeJwt($user->id, admin: true))
            ->getJson('/api/v1/referral-bonus/my-list')
            ->assertStatus(200)
            ->assertJson(['status' => true]);
    }
}
