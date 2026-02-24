<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Enums\AccessLevels;
use App\Enums\Roles;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @OA\Tag(name="User")
 */
class UserController extends Controller
{
    /**
     * @OA\Patch(
     *     path="/api/v1/user/fcm-token",
     *     summary="Update user FCM token",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fcm_token"},
     *             @OA\Property(property="fcm_token", type="string", example="dGhpcyBpcyBhIHRlc3QgdG9rZW4...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FCM token updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateFcmToken(Request $request, UserService $userService): JsonResponse
    {
        $userId = $userService->extractIdFromRequest($request);
        $user = User::find($userId);

        if (!$user) {
            return ApiResponseClass::sendError('User not found', 404, 404);
        }

        $userRoles = $user->roles ?? '';

        $hasAccess = collect(AccessLevels::LEVEL_1->roles())->contains(
            fn(Roles $role) => str_contains($userRoles, $role->value)
        );

        if (!$hasAccess) {
            return ApiResponseClass::sendError('Forbidden', 403, 403);
        }

        $request->validate([
            'token' => 'required|string',
        ]);

        $user->fcm_token = $request->token;
        $user->save();

        return ApiResponseClass::sendSuccess();
    }
}
