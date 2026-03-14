<?php

namespace Tests\Feature\ReferralBonus;

use App\Models\ReferralBonus;
use App\Models\User;
use App\Services\ReferralBonusService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralBonusServiceTest extends TestCase
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

    private function service(): ReferralBonusService
    {
        return app(ReferralBonusService::class);
    }

    // ---------------------------------------------------------------
    // createBonus – self-referral guard
    // ---------------------------------------------------------------

    public function test_create_bonus_skips_when_requester_owns_the_code(): void
    {
        $owner = $this->createUser(['referral_code' => 'MYOWNCODE']);

        // Simulate the owner making the request
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn((string) $owner->id)
        );

        $this->service()->createBonus('MYOWNCODE', '999', ReferralBonus::TYPE_LISTING);

        $this->assertDatabaseMissing('referral_bonuses', [
            'referral_code' => 'MYOWNCODE',
            'reference_id'  => '999',
        ]);
    }

    public function test_create_bonus_succeeds_when_requester_is_different_from_code_owner(): void
    {
        $owner     = $this->createUser(['referral_code' => 'THEIRCODE']);
        $requester = $this->createUser();

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn((string) $requester->id)
        );

        $this->service()->createBonus('THEIRCODE', '888', ReferralBonus::TYPE_LISTING);

        $this->assertDatabaseHas('referral_bonuses', [
            'referral_code' => 'THEIRCODE',
            'reference_id'  => '888',
            'user_id'       => (string) $owner->id,
            'amount'        => 100,
            'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
            'type'          => ReferralBonus::TYPE_LISTING,
        ]);
    }

    public function test_create_bonus_succeeds_when_no_authenticated_user(): void
    {
        $owner = $this->createUser(['referral_code' => 'ANONCODE']);

        // No authenticated user (unauthenticated request)
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn(null)
        );

        $this->service()->createBonus('ANONCODE', '777', ReferralBonus::TYPE_VIEWING);

        $this->assertDatabaseHas('referral_bonuses', [
            'referral_code' => 'ANONCODE',
            'reference_id'  => '777',
            'user_id'       => (string) $owner->id,
        ]);
    }

    public function test_create_bonus_sets_null_user_id_when_code_owner_not_found(): void
    {
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn(null)
        );

        $this->service()->createBonus('UNKNOWNCODE', '666', ReferralBonus::TYPE_RENTING);

        $bonus = ReferralBonus::where('referral_code', 'UNKNOWNCODE')
            ->where('reference_id', '666')
            ->first();

        $this->assertNotNull($bonus);
        $this->assertNull($bonus->user_id);
    }

    // ---------------------------------------------------------------
    // updateBonus – existing bonus
    // ---------------------------------------------------------------

    public function test_update_bonus_changes_referral_code_and_user_and_appends_metadata(): void
    {
        $oldOwner = $this->createUser(['referral_code' => 'OLDCODE']);
        $newOwner = $this->createUser(['referral_code' => 'NEWCODE']);

        $bonus = ReferralBonus::create([
            'user_id'       => $oldOwner->id,
            'referral_code' => 'OLDCODE',
            'amount'        => 100,
            'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
            'type'          => ReferralBonus::TYPE_LISTING,
            'reference_id'  => '100',
        ]);

        $this->service()->updateBonus('OLDCODE', 'NEWCODE', '100', ReferralBonus::TYPE_LISTING);

        $bonus->refresh();
        $this->assertSame('NEWCODE', $bonus->referral_code);
        $this->assertSame((string) $newOwner->id, (string) $bonus->user_id);

        $changes = $bonus->metadata['changes'];
        $this->assertCount(1, $changes);
        $this->assertSame('OLDCODE', $changes[0]['old_referral_code']);
        $this->assertSame('NEWCODE', $changes[0]['new_referral_code']);
        $this->assertSame((string) $oldOwner->id, (string) $changes[0]['old_user_id']);
        $this->assertSame((string) $newOwner->id, (string) $changes[0]['new_user_id']);
    }

    public function test_update_bonus_accumulates_multiple_changes_in_metadata(): void
    {
        $this->createUser(['referral_code' => 'CODE-V1']);
        $this->createUser(['referral_code' => 'CODE-V2']);
        $this->createUser(['referral_code' => 'CODE-V3']);

        ReferralBonus::create([
            'referral_code' => 'CODE-V1',
            'amount'        => 100,
            'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
            'type'          => ReferralBonus::TYPE_VIEWING,
            'reference_id'  => '200',
        ]);

        $this->service()->updateBonus('CODE-V1', 'CODE-V2', '200', ReferralBonus::TYPE_VIEWING);
        $this->service()->updateBonus('CODE-V2', 'CODE-V3', '200', ReferralBonus::TYPE_VIEWING);

        $bonus = ReferralBonus::where('reference_id', '200')
            ->where('type', ReferralBonus::TYPE_VIEWING)
            ->first();

        $this->assertCount(2, $bonus->metadata['changes']);
        $this->assertSame('CODE-V3', $bonus->referral_code);
    }

    // ---------------------------------------------------------------
    // updateBonus – fallback create path
    // ---------------------------------------------------------------

    public function test_update_bonus_creates_new_bonus_when_none_exists(): void
    {
        $owner = $this->createUser(['referral_code' => 'NEWREF']);

        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn(null)
        );

        // No existing bonus for reference 300/TYPE_RENTING
        $this->service()->updateBonus(null, 'NEWREF', '300', ReferralBonus::TYPE_RENTING);

        $this->assertDatabaseHas('referral_bonuses', [
            'referral_code' => 'NEWREF',
            'reference_id'  => '300',
            'user_id'       => (string) $owner->id,
        ]);
    }

    public function test_update_bonus_does_nothing_when_new_code_is_null_and_no_bonus_exists(): void
    {
        $countBefore = ReferralBonus::count();

        $this->service()->updateBonus('OLDCODE', null, '400', ReferralBonus::TYPE_LISTING);

        $this->assertSame($countBefore, ReferralBonus::count());
    }

    public function test_update_bonus_does_not_update_when_new_code_is_null_and_bonus_exists(): void
    {
        $bonus = ReferralBonus::create([
            'referral_code' => 'KEEPME',
            'amount'        => 100,
            'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
            'type'          => ReferralBonus::TYPE_LISTING,
            'reference_id'  => '500',
        ]);

        $this->service()->updateBonus('KEEPME', null, '500', ReferralBonus::TYPE_LISTING);

        $bonus->refresh();
        $this->assertSame('KEEPME', $bonus->referral_code); // unchanged
        $this->assertNull($bonus->metadata);               // no history entry
    }
}
