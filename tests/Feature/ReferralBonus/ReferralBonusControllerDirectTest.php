<?php

namespace Tests\Feature\ReferralBonus;

use App\Http\Controllers\ReferralBonusController;
use App\Models\ReferralBonus;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralBonusControllerDirectTest extends TestCase
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

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'          => 'Test User',
            'email'         => 'test-' . Str::random(8) . '@example.com',
            'phone'         => '+316' . mt_rand(10000000, 99999999),
            'referral_code' => 'REF-' . Str::random(6),
        ], $overrides));
    }

    private function createBonus(array $overrides = []): ReferralBonus
    {
        return ReferralBonus::create(array_merge([
            'referral_code' => 'TESTCODE',
            'amount'        => 100,
            'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
            'type'          => ReferralBonus::TYPE_LISTING,
            'reference_id'  => (string) mt_rand(10000, 99999),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // list
    // ---------------------------------------------------------------

    public function test_list_returns_paginated_results(): void
    {
        $this->createBonus(['referral_code' => 'CODE-A', 'type' => ReferralBonus::TYPE_LISTING]);
        $this->createBonus(['referral_code' => 'CODE-B', 'type' => ReferralBonus::TYPE_VIEWING]);

        $request  = Request::create('/api/v1/referral-bonus/list', 'GET', ['per_page' => 5]);
        $response = app(ReferralBonusController::class)->list($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertArrayHasKey('data', $payload['data']);
        $this->assertArrayHasKey('total', $payload['data']);
        $this->assertGreaterThanOrEqual(2, $payload['data']['total']);
    }

    public function test_list_filters_by_status(): void
    {
        $this->createBonus(['status' => ReferralBonus::STATUS_COMPLETED]);
        $this->createBonus(['status' => ReferralBonus::STATUS_REJECTED]);

        $request  = Request::create('/api/v1/referral-bonus/list', 'GET', ['status' => ReferralBonus::STATUS_COMPLETED]);
        $response = app(ReferralBonusController::class)->list($request);

        $payload = $this->assertJsonStatus($response, 200);
        foreach ($payload['data']['data'] as $item) {
            $this->assertSame(ReferralBonus::STATUS_COMPLETED, $item['status']);
        }
    }

    public function test_list_filters_by_type(): void
    {
        $this->createBonus(['type' => ReferralBonus::TYPE_RENTING]);
        $this->createBonus(['type' => ReferralBonus::TYPE_VIEWING]);

        $request  = Request::create('/api/v1/referral-bonus/list', 'GET', ['type' => ReferralBonus::TYPE_RENTING]);
        $response = app(ReferralBonusController::class)->list($request);

        $payload = $this->assertJsonStatus($response, 200);
        foreach ($payload['data']['data'] as $item) {
            $this->assertSame(ReferralBonus::TYPE_RENTING, $item['type']);
        }
    }

    public function test_list_filters_by_referral_code_partial_match(): void
    {
        $unique = 'UNIQUEREF' . Str::random(4);
        $this->createBonus(['referral_code' => $unique]);

        $request  = Request::create('/api/v1/referral-bonus/list', 'GET', ['referral_code' => 'UNIQUEREF']);
        $response = app(ReferralBonusController::class)->list($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertGreaterThanOrEqual(1, $payload['data']['total']);
    }

    // ---------------------------------------------------------------
    // show
    // ---------------------------------------------------------------

    public function test_show_returns_bonus_by_id(): void
    {
        $bonus    = $this->createBonus();
        $response = app(ReferralBonusController::class)->show($bonus->id);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame($bonus->id, $payload['data']['id']);
        $this->assertSame($bonus->referral_code, $payload['data']['referral_code']);
    }

    public function test_show_returns_400_for_unknown_id(): void
    {
        $response = app(ReferralBonusController::class)->show(999999999);

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // create
    // ---------------------------------------------------------------

    public function test_create_persists_bonus_and_returns_200(): void
    {
        $request = Request::create('/api/v1/referral-bonus/create', 'POST', [
            'referral_code' => 'PROMO2026',
            'type'          => ReferralBonus::TYPE_LISTING,
            'reference_id'  => '501',
            'amount'        => 150,
            'status'        => ReferralBonus::STATUS_PENDING,
            'public_note'   => 'Great referral',
            'internal_note' => 'Verified manually',
        ]);

        $response = app(ReferralBonusController::class)->create($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('PROMO2026', $payload['data']['referral_code']);
        $this->assertSame(150, $payload['data']['amount']);
        $this->assertSame(ReferralBonus::STATUS_PENDING, $payload['data']['status']);
        $this->assertArrayNotHasKey('user_id', $payload['data']);
    }

    public function test_create_uses_defaults_for_amount_and_status(): void
    {
        $request = Request::create('/api/v1/referral-bonus/create', 'POST', [
            'referral_code' => 'DEFAULT-TEST',
            'type'          => ReferralBonus::TYPE_VIEWING,
            'reference_id'  => '502',
        ]);

        $response = app(ReferralBonusController::class)->create($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertSame(100, $payload['data']['amount']);
        $this->assertSame(ReferralBonus::STATUS_WAITING_APPROVAL, $payload['data']['status']);
    }

    public function test_create_works_with_referral_code_not_linked_to_any_account(): void
    {
        $request = Request::create('/api/v1/referral-bonus/create', 'POST', [
            'referral_code' => 'UNREGISTERED-CODE',
            'type'          => ReferralBonus::TYPE_RENTING,
            'reference_id'  => '503',
        ]);

        $response = app(ReferralBonusController::class)->create($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame('UNREGISTERED-CODE', $payload['data']['referral_code']);
    }

    public function test_create_returns_422_when_required_fields_missing(): void
    {
        $request = Request::create('/api/v1/referral-bonus/create', 'POST', [
            'referral_code' => 'PARTIAL',
            // missing type and reference_id
        ]);

        $response = app(ReferralBonusController::class)->create($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_create_returns_422_for_invalid_type(): void
    {
        $request = Request::create('/api/v1/referral-bonus/create', 'POST', [
            'referral_code' => 'BADTYPE',
            'type'          => 99,
            'reference_id'  => '504',
        ]);

        $response = app(ReferralBonusController::class)->create($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // edit
    // ---------------------------------------------------------------

    public function test_edit_updates_bonus_fields(): void
    {
        $bonus = $this->createBonus(['status' => ReferralBonus::STATUS_WAITING_APPROVAL]);

        $request = Request::create('/api/v1/referral-bonus/edit', 'PATCH', [
            'id'            => $bonus->id,
            'status'        => ReferralBonus::STATUS_COMPLETED,
            'internal_note' => 'Approved by admin',
        ]);

        $response = app(ReferralBonusController::class)->edit($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(ReferralBonus::STATUS_COMPLETED, $payload['data']['status']);
        $this->assertSame('Approved by admin', $payload['data']['internal_note']);
    }

    public function test_edit_returns_400_for_unknown_id(): void
    {
        $request = Request::create('/api/v1/referral-bonus/edit', 'PATCH', [
            'id'     => 999999999,
            'status' => ReferralBonus::STATUS_COMPLETED,
        ]);

        $response = app(ReferralBonusController::class)->edit($request);

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_edit_returns_422_for_invalid_status(): void
    {
        $bonus = $this->createBonus();

        $request = Request::create('/api/v1/referral-bonus/edit', 'PATCH', [
            'id'     => $bonus->id,
            'status' => 99,
        ]);

        $response = app(ReferralBonusController::class)->edit($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------

    public function test_destroy_deletes_bonus(): void
    {
        $bonus    = $this->createBonus();
        $request  = Request::create('/api/v1/referral-bonus/delete', 'DELETE', ['id' => $bonus->id]);
        $response = app(ReferralBonusController::class)->destroy($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertNull(ReferralBonus::find($bonus->id));
    }

    public function test_destroy_returns_400_for_unknown_id(): void
    {
        $request  = Request::create('/api/v1/referral-bonus/delete', 'DELETE', ['id' => 999999999]);
        $response = app(ReferralBonusController::class)->destroy($request);

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // myList
    // ---------------------------------------------------------------

    public function test_my_list_returns_401_when_unauthenticated(): void
    {
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn(null)
        );

        $request  = Request::create('/api/v1/referral-bonus/my-list', 'GET');
        $response = app(ReferralBonusController::class)->myList($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 401);
        $this->assertFalse($payload['status']);
    }

    public function test_my_list_returns_bonuses_matched_by_users_referral_code(): void
    {
        $user = $this->createUser(['referral_code' => 'MY-CODE-' . Str::random(4)]);

        $this->createBonus(['referral_code' => $user->referral_code]);
        $this->createBonus(['referral_code' => $user->referral_code]);
        $this->createBonus(['referral_code' => 'OTHER-CODE']); // should not appear

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn($user->id)
        );

        $request  = Request::create('/api/v1/referral-bonus/my-list', 'GET');
        $response = app(ReferralBonusController::class)->myList($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(2, $payload['data']['total']);
        foreach ($payload['data']['data'] as $item) {
            $this->assertSame($user->referral_code, $item['referral_code']);
        }
    }

    public function test_my_list_returns_empty_when_user_has_no_referral_code(): void
    {
        $user = $this->createUser(['referral_code' => '']);

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn($user->id)
        );

        $request  = Request::create('/api/v1/referral-bonus/my-list', 'GET');
        $response = app(ReferralBonusController::class)->myList($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(0, $payload['data']['total']);
    }
}
