<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        $user = $userService->getUserByRequest($request);

        if (!$user) {
            return ApiResponseClass::sendError('User not found', 404, 404);
        }

        if (!$userService->hasLevel1Access($user)) {
            return ApiResponseClass::sendError('Forbidden', 403, 403);
        }

        $request->validate([
            'token' => 'required|string',
        ]);

        $userService->updateFcmToken($user, $request->input('token'));

        return ApiResponseClass::sendSuccess();
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/user/referral-code",
     *     summary="Update user referral code",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"referral_code"},
     *             @OA\Property(property="referral_code", type="string", minLength=4, example="VLAD2024")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Referral code updated successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateReferralCode(Request $request, UserService $userService): JsonResponse
    {
        $user = $userService->getUserByRequest($request);

        if (!$user) {
            return ApiResponseClass::sendError('User not found', 404, 404);
        }

        $messages = [
            'referralCode.required' => ['tag' => 'account:profile.errors.referral_code_required'],
            'referralCode.min'      => ['tag' => 'account:profile.errors.referral_code_too_short'],
            'referralCode.unique'   => ['tag' => 'account:profile.errors.referral_code_taken'],
        ];

        $validator = Validator::make($request->all(), [
            'referralCode' => [
                'required',
                'string',
                'min:4',
                Rule::unique('users', 'referral_code')->ignore($user->id),
            ],
        ], $messages);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), $messages);
        }

        $userService->updateReferralCode($user, $request->input('referralCode'));

        return ApiResponseClass::sendSuccess(['referral_code' => $user->referral_code]);
    }
}
