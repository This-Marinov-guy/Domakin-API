<?php

namespace App\Services;

use App\Models\ReferralBonus;
use Illuminate\Support\Facades\Log;

class ReferralBonusService
{
    /**
     * Create a referral bonus for a newly created object that carries a referral code.
     */
    public function createBonus(string $referralCode, string $referenceId, int $type): void
    {
        try {
            ReferralBonus::create([
                'referral_code' => $referralCode,
                'amount'        => 100,
                'status'        => ReferralBonus::STATUS_WAITING_APPROVAL,
                'type'          => $type,
                'reference_id'  => $referenceId,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReferralBonusService::createBonus failed', [
                'referral_code' => $referralCode,
                'reference_id'  => $referenceId,
                'type'          => $type,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a referral code change on an existing object.
     * Updates the bonus row and appends the change to metadata history.
     * If no bonus exists yet (e.g. code was just added), creates one instead.
     */
    public function updateBonus(
        ?string $oldReferralCode,
        ?string $newReferralCode,
        string $referenceId,
        int $type
    ): void {
        try {
            $bonus = ReferralBonus::where('type', $type)
                ->where('reference_id', $referenceId)
                ->first();

            if ($bonus === null) {
                if ($newReferralCode) {
                    $this->createBonus($newReferralCode, $referenceId, $type);
                }
                return;
            }

            if (!$newReferralCode) {
                // Referral code was removed — nothing more to update
                return;
            }

            $metadata = $bonus->metadata ?? [];
            $metadata['changes'][] = [
                'changed_at'        => now()->toIso8601String(),
                'old_referral_code' => $oldReferralCode,
                'new_referral_code' => $newReferralCode,
            ];

            $bonus->update([
                'referral_code' => $newReferralCode,
                'metadata'      => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReferralBonusService::updateBonus failed', [
                'old_referral_code' => $oldReferralCode,
                'new_referral_code' => $newReferralCode,
                'reference_id'      => $referenceId,
                'type'              => $type,
                'error'             => $e->getMessage(),
            ]);
        }
    }
}
