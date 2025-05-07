<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;

class UserService
{
    public function extractIdFromRequest($request)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return null;
            }

            $key = new \Firebase\JWT\Key(env('SUPABASE_JWT_SECRET'), 'HS256');
            $decoded = JWT::decode($token, $key);
            $userId = $decoded->sub ?? null;
        } catch (\Exception $e) {
            $userId = null;
        }

        return $userId;
    }

    public function getUserByRequest($request)
    {
        return User::find($this->extractIdFromRequest($request));
    }
}
