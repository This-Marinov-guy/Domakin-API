<?php

namespace App\Observers;

use App\Models\ReferralBonus;
use App\Models\Viewing;
use App\Services\ReferralBonusService;

class ViewingObserver
{
    public function __construct(private ReferralBonusService $referralBonusService)
    {
    }

    public function created(Viewing $viewing): void
    {
        if ($viewing->referral_code) {
            $this->referralBonusService->createBonus(
                $viewing->referral_code,
                (string) $viewing->id,
                ReferralBonus::TYPE_VIEWING
            );
        }
    }

    public function updated(Viewing $viewing): void
    {
        if ($viewing->wasChanged('referral_code')) {
            $this->referralBonusService->updateBonus(
                $viewing->getOriginal('referral_code'),
                $viewing->referral_code,
                (string) $viewing->id,
                ReferralBonus::TYPE_VIEWING
            );
        }
    }
}
