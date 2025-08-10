<?php

namespace App\Services;

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

    public function getUserByRequest($request)
    {
        return User::find($this->extractIdFromRequest($request));
    }
}
