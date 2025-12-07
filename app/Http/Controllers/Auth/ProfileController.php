<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Files\CloudinaryService;
use App\Services\UserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Classes\ApiResponseClass;
use App\Services\Helpers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="User")
 */
class ProfileController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/user/edit-details",
     *     summary="Update user profile",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+31 6 12345678"),
     *                 @OA\Property(property="password", type="string", description="New password (optional)"),
     *                 @OA\Property(property="profileImage", type="string", format="binary", description="Profile image (optional)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string"), example={"email", "phone"}),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"), example={"account:authentication.errors.email", "account:authentication.errors.phone_invalid"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message"),
     *             @OA\Property(property="tag", type="string", example="account:authentication.errors.general")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update profile"),
     *             @OA\Property(property="tag", type="string", example="account:authentication.errors.general")
     *         )
     *     )
     * )
     */
    public function edit(Request $request, CloudinaryService $cloudinary, UserService $userService): JsonResponse
    {
        $user = $userService->getUserByRequest($request);

        $validator = Validator::make($request->all(), User::rulesEdit($user->id), User::messages());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), User::messages());
        }

        if ($request->password) {
            $validator = Validator::make($request->all(), User::rulesPassword(), User::messages());

            if ($validator->fails()) {
                return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), User::messages());
            }
        }

        try {
            $supabaseUpdateData = [];

            if ($request->email !== $user->email) {
                $supabaseUpdateData['email'] = $request->email;
            }

            if ($request->phone !== $user->phone) {
                $supabaseUpdateData['phone'] = $request->phone;
            }

            if ($request->name !== $user->name) {
                $supabaseUpdateData['user_metadata']['display_name'] = $request->name;
            }

            if ($request->password) {
                $supabaseUpdateData['password'] = Hash::make($request->password);
            }

            $profileImageUrl = $request->hasFile('profileImage') ? $cloudinary->singleUpload($request->file('profileImage'), [
                'folder' => "profiles/" . $user->id,
            ]) : null;

            if (!empty($supabaseUpdateData)) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('supabase.service_role_key'),
                    'Content-Type' => 'application/json',
                    'apikey' => config('supabase.service_role_key'),
                ])->put(rtrim(config('supabase.url'), '/') . '/auth/v1/admin/users/' . $user->id, $supabaseUpdateData);
    
    
                if (!$response->successful()) {
                    throw new \Exception('Supabase update failed: ' . $response->body());
                }

                $user->fill($request->only(keys: ['email', 'phone', 'name']));
            }

            if (!empty($profileImageUrl)) {
                $user->profile_image = $profileImageUrl;
            }

            $user->save();

            return ApiResponseClass::sendSuccess([
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return ApiResponseClass::sendError('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }
}
