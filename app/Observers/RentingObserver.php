<?php

namespace App\Observers;

use App\Models\Renting;
use App\Models\ReferralBonus;
use App\Services\ReferralBonusService;

class RentingObserver
{
    public function __construct(private ReferralBonusService $referralBonusService)
    {
    }

    public function created(Renting $renting): void
    {
        if ($renting->referral_code) {
            $this->referralBonusService->createBonus(
                $renting->referral_code,
                (string) $renting->id,
                ReferralBonus::TYPE_RENTING
            );
        }
    }

    public function updated(Renting $renting): void
    {
        if ($renting->wasChanged('referral_code')) {
            $this->referralBonusService->updateBonus(
                $renting->getOriginal('referral_code'),
                $renting->referral_code,
                (string) $renting->id,
                ReferralBonus::TYPE_RENTING
            );
        }
    }
}
