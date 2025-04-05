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
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function edit(Request $request, CloudinaryService $cloudinary, UserService $userService): JsonResponse
    {
        $validator = Validator::make($request->all(), User::rulesEdit(), User::messages());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), User::messages());
        }

        if ($request->password) {
            $validator = Validator::make($request->all(), User::rulesPassword(), User::messages());

            if ($validator->fails()) {
                return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), User::messages());
            }
        }

        $user = $userService->getUserByRequest($request);

        try {
            $supabaseUpdateData = [
                'email' => $request->email,
                'phone' => $request->phone,
                'user_metadata' => [
                    'display_name' => $request->name
                ]
            ];

            if ($request->password) {
                $supabaseUpdateData['password'] = Hash::make($request->password);
            }

            $profileImageUrl = $request->hasFile('profile_image') ? $cloudinary->singleUpload($request->file('profile_image'), [
                'folder' => "profiles/" . $user->id,
            ]) : null;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
                'Content-Type' => 'application/json',
                'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
            ])->put(env('SUPABASE_URL') . '/auth/v1/admin/users/' . $user->id, $supabaseUpdateData);


            if (!$response->successful()) {
                throw new \Exception('Supabase update failed: ' . $response->body());
            }

            $user->fill($request->only(['email', 'phone', 'name']));

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
