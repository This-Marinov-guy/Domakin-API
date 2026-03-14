<?php

namespace Tests\Feature\ReferralBonus;

use App\Models\Property;
use App\Models\ReferralBonus;
use App\Models\Renting;
use App\Models\User;
use App\Models\Viewing;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralBonusObserverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        try {
            DB::reconnect();
        } catch (\Throwable) {
        }
        parent::setUp();

        // No authenticated requester by default → self-referral guard never blocks
        $this->mock(UserService::class, fn ($m) =>
            $m->shouldReceive('extractIdFromRequest')->andReturn(null)
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

    private function createUser(string $referralCode): User
    {
        return User::create([
            'name'          => 'Test User',
            'email'         => 'test-' . Str::random(8) . '@example.com',
            'phone'         => '+316' . mt_rand(10000000, 99999999),
            'referral_code' => $referralCode,
        ]);
    }

    // ---------------------------------------------------------------
    // Property observer
    // ---------------------------------------------------------------

    public function test_creating_property_with_referral_code_creates_bonus(): void
    {
        $owner    = $this->createUser('PROP-REF');
        $property = Property::create([
            'interface'       => 'web',
            'referral_code'   => 'PROP-REF',
        ]);

        $this->assertDatabaseHas('referral_bonuses', [
            'referral_code' => 'PROP-REF',
            'reference_id'  => (string) $property->id,
            'type'          => ReferralBonus::TYPE_LISTING,
            'user_id'       => (string) $owner->id,
            'amount'        => 100,
            'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
        ]);
    }

    public function test_creating_property_without_referral_code_does_not_create_bonus(): void
    {
        $countBefore = ReferralBonus::count();

        Property::create(['interface' => 'web']);

        $this->assertSame($countBefore, ReferralBonus::count());
    }

    public function test_updating_property_referral_code_updates_bonus(): void
    {
        $this->createUser('PROP-OLD');
        $newOwner = $this->createUser('PROP-NEW');

        $property = Property::create([
            'interface'     => 'web',
            'referral_code' => 'PROP-OLD',
        ]);

        // Confirm initial bonus was created
        $bonus = ReferralBonus::where('reference_id', (string) $property->id)
            ->where('type', ReferralBonus::TYPE_LISTING)
            ->firstOrFail();

        $property->update(['referral_code' => 'PROP-NEW']);

        $bonus->refresh();
        $this->assertSame('PROP-NEW', $bonus->referral_code);
        $this->assertSame((string) $newOwner->id, (string) $bonus->user_id);

        // Change is recorded in metadata
        $this->assertSame('PROP-OLD', $bonus->metadata['changes'][0]['old_referral_code']);
        $this->assertSame('PROP-NEW', $bonus->metadata['changes'][0]['new_referral_code']);
    }

    public function test_updating_property_without_changing_referral_code_does_not_add_metadata(): void
    {
        $this->createUser('PROP-STABLE');

        $property = Property::create([
            'interface'     => 'web',
            'referral_code' => 'PROP-STABLE',
        ]);

        $property->update(['interface' => 'mobile']);

        $bonus = ReferralBonus::where('reference_id', (string) $property->id)
            ->where('type', ReferralBonus::TYPE_LISTING)
            ->first();

        $this->assertNull($bonus->metadata); // no changes appended
    }

    // ---------------------------------------------------------------
    // Viewing observer
    // ---------------------------------------------------------------

    public function test_creating_viewing_with_referral_code_creates_bonus(): void
    {
        $owner   = $this->createUser('VIEW-REF');
        $viewing = Viewing::create([
            'name'          => 'Jane',
            'surname'       => 'Doe',
            'phone'         => '+31600000001',
            'email'         => 'jane@example.com',
            'city'          => 'Amsterdam',
            'address'       => 'Herengracht 1',
            'date'          => '2026-04-01',
            'time'          => '10:00',
            'interface'     => 'web',
            'referral_code' => 'VIEW-REF',
        ]);

        $this->assertDatabaseHas('referral_bonuses', [
            'referral_code' => 'VIEW-REF',
            'reference_id'  => (string) $viewing->id,
            'type'          => ReferralBonus::TYPE_VIEWING,
            'user_id'       => (string) $owner->id,
        ]);
    }

    public function test_creating_viewing_without_referral_code_does_not_create_bonus(): void
    {
        $countBefore = ReferralBonus::count();

        Viewing::create([
            'name'      => 'No',
            'surname'   => 'Ref',
            'phone'     => '+31600000002',
            'email'     => 'noref@example.com',
            'city'      => 'Amsterdam',
            'address'   => 'Keizersgracht 1',
            'date'      => '2026-04-01',
            'time'      => '11:00',
            'interface' => 'web',
        ]);

        $this->assertSame($countBefore, ReferralBonus::count());
    }

    public function test_updating_viewing_referral_code_updates_bonus(): void
    {
        $this->createUser('VIEW-OLD');
        $newOwner = $this->createUser('VIEW-NEW');

        $viewing = Viewing::create([
            'name'          => 'Jane',
            'surname'       => 'Doe',
            'phone'         => '+31600000003',
            'email'         => 'update@example.com',
            'city'          => 'Amsterdam',
            'address'       => 'Prinsengracht 1',
            'date'          => '2026-04-02',
            'time'          => '12:00',
            'interface'     => 'web',
            'referral_code' => 'VIEW-OLD',
        ]);

        $viewing->update(['referral_code' => 'VIEW-NEW']);

        $bonus = ReferralBonus::where('reference_id', (string) $viewing->id)
            ->where('type', ReferralBonus::TYPE_VIEWING)
            ->firstOrFail();

        $this->assertSame('VIEW-NEW', $bonus->referral_code);
        $this->assertSame((string) $newOwner->id, (string) $bonus->user_id);
    }

    // ---------------------------------------------------------------
    // Renting observer
    // ---------------------------------------------------------------

    public function test_creating_renting_with_referral_code_creates_bonus(): void
    {
        $owner   = $this->createUser('RENT-REF');
        $renting = Renting::create([
            'property'      => 'Test Property',
            'name'          => 'John',
            'surname'       => 'Doe',
            'phone'         => '+31600000004',
            'email'         => 'john@example.com',
            'letter'        => 'I am interested.',
            'interface'     => 'web',
            'referral_code' => 'RENT-REF',
        ]);

        $this->assertDatabaseHas('referral_bonuses', [
            'referral_code' => 'RENT-REF',
            'reference_id'  => (string) $renting->id,
            'type'          => ReferralBonus::TYPE_RENTING,
            'user_id'       => (string) $owner->id,
        ]);
    }

    public function test_creating_renting_without_referral_code_does_not_create_bonus(): void
    {
        $countBefore = ReferralBonus::count();

        Renting::create([
            'property'  => 'Test Property',
            'name'      => 'No',
            'surname'   => 'Ref',
            'phone'     => '+31600000005',
            'email'     => 'noref2@example.com',
            'letter'    => 'I am interested.',
            'interface' => 'web',
        ]);

        $this->assertSame($countBefore, ReferralBonus::count());
    }

    public function test_updating_renting_referral_code_updates_bonus(): void
    {
        $this->createUser('RENT-OLD');
        $newOwner = $this->createUser('RENT-NEW');

        $renting = Renting::create([
            'property'      => 'Test Property',
            'name'          => 'John',
            'surname'       => 'Doe',
            'phone'         => '+31600000006',
            'email'         => 'update2@example.com',
            'letter'        => 'Interested.',
            'interface'     => 'web',
            'referral_code' => 'RENT-OLD',
        ]);

        $renting->update(['referral_code' => 'RENT-NEW']);

        $bonus = ReferralBonus::where('reference_id', (string) $renting->id)
            ->where('type', ReferralBonus::TYPE_RENTING)
            ->firstOrFail();

        $this->assertSame('RENT-NEW', $bonus->referral_code);
        $this->assertSame((string) $newOwner->id, (string) $bonus->user_id);
    }
}
