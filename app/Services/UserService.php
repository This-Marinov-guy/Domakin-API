<?php

namespace App\Services;

use App\Enums\AccessLevels;
use App\Enums\Roles;
use App\Models\User;
use Firebase\JWT\JWT;

class UserService
{
    public function extractIdFromRequest($request)
    {
        $userId = null;

        try {
            $token = $request->bearerToken();

            if ($token) {
                $jwtSecret = config('supabase.jwt_secret');
                $key = new \Firebase\JWT\Key($jwtSecret, 'HS256');
                $decoded = JWT::decode($token, $key);
                $userId = $decoded->sub ?? null;
            }
        } catch (\Throwable $e) {
            // Optional: log the error if needed
            // \Log::error('JWT decode failed: ' . $e->getMessage());
        }

        return $userId;
    }

    public function getUserByRequest($request): ?User
    {
        return User::find($this->extractIdFromRequest($request));
    }

    public function hasLevel1Access(User $user): bool
    {
        $userRoles = $user->roles ?? '';

        return collect(AccessLevels::LEVEL_1->roles())->contains(
            fn(Roles $role) => str_contains($userRoles, $role->value)
        );
    }

    public function updateFcmToken(User $user, string $fcmToken): void
    {
        $user->fcm_token = $fcmToken;
        $user->save();
    }

    public function updateReferralCode(User $user, string $referralCode): void
    {
        $user->referral_code = $referralCode;
        $user->save();
    }
}
