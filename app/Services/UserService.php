<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;

class UserService
{
    public function extractIdFromRequest($request)
    {
        $token = $request->bearerToken();
        $key = new \Firebase\JWT\Key(config('app.key'), 'HS256');
        $decoded = JWT::decode($token, $key);
        $userId = $decoded->sub;

        return $userId;
    }

    public function getUserByRequest($request)
    {
        return User::find($this->extractIdFromRequest($request));
    }
}
