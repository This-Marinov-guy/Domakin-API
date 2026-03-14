<?php

namespace App\Services;

use App\Enums\AccessLevels;
use App\Enums\Roles;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;

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

    /**
     * List users, optionally filtered by agent role, with pagination and name/referral_code search.
     */
    public function listUsers(Request $request, ?string $search = null, bool $agentsOnly = false): array
    {
        $query = User::query();

        if ($agentsOnly) {
            $query->where('roles', 'LIKE', '%agent%');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('referral_code', 'LIKE', "%{$search}%");
            });
        }

        $perPage = max(1, (int) $request->input('per_page', 15));
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'users' => array_map(fn(User $u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'referral_code' => $u->referral_code,
                'roles'         => $u->roles,
                'profile_image' => $u->profile_image,
            ], $paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    /**
     * Bulk add or remove a role for the given user IDs.
     *
     * @param string[] $userIds
     * @param string   $role   e.g. 'agent'
     * @param string   $action 'add' | 'remove'
     */
    public function updateUserRoles(array $userIds, string $role, string $action): void
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $roles = array_filter(array_map('trim', explode(',', $user->roles ?? '')));

            if ($action === 'add' && !in_array($role, $roles, true)) {
                $roles[] = $role;
            } elseif ($action === 'remove') {
                $roles = array_values(array_filter($roles, fn($r) => $r !== $role));
            }

            // Always keep 'user' as the base role
            if (empty($roles)) {
                $roles = ['user'];
            }

            $user->roles = implode(',', array_unique($roles));
            $user->save();
        }
    }
}
