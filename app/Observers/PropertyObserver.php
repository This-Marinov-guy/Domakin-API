<?php

namespace App\Observers;

use App\Models\Property;
use App\Models\ReferralBonus;
use App\Services\ReferralBonusService;

class PropertyObserver
{
    public function __construct(private ReferralBonusService $referralBonusService)
    {
    }

    public function created(Property $property): void
    {
        if ($property->referral_code) {
            $this->referralBonusService->createBonus(
                $property->referral_code,
                (string) $property->id,
                ReferralBonus::TYPE_LISTING
            );
        }
    }

    public function updated(Property $property): void
    {
        if ($property->wasChanged('referral_code')) {
            $this->referralBonusService->updateBonus(
                $property->getOriginal('referral_code'),
                $property->referral_code,
                (string) $property->id,
                ReferralBonus::TYPE_LISTING
            );
        }
    }
}
