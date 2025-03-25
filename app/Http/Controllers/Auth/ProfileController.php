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

        $user = $userService->getUserByRequest($request);

        try {
            // Prepare update data for Supabase
            $supabaseUpdateData = [];

            if ($request->email && $request->email !== $user->email) {
                $supabaseUpdateData['email'] = $request->email;
            }

            if ($request->phone && $request->phone !== $user->phone) {
                $supabaseUpdateData['phone'] = $request->phone;
            }

            if ($request->displayName) {
                $supabaseUpdateData['user_metadata']['displayName'] = $request->displayName;
            }

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $profileImageUrl = $cloudinary->multiUpload($request->file('profile_image'), [
                    'folder' => "profiles/" . $user->id,
                ]);
                $supabaseUpdateData['user_metadata']['profile_image'] = $profileImageUrl;
            }

            // Update Supabase
            if (!empty($supabaseUpdateData)) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
                    'Content-Type' => 'application/json'
                ])->patch(env('SUPABASE_URL') . '/auth/v1/admin/users/' . $user->supabase_id, $supabaseUpdateData);

                if (!$response->successful()) {
                    throw new \Exception('Supabase update failed: ' . $response->body());
                }
            }

            // Update Laravel database
            $user->fill($request->only(['email', 'phone']));
            $user->display_name = $request->displayName ?? $user->display_name;

            if (isset($profileImageUrl)) {
                $user->profile_image = $profileImageUrl;
            }

            if ($request->password) {
                $user->password = Hash::make($request->password);

                // Update password in Supabase
                Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
                    'Content-Type' => 'application/json'
                ])->patch(env('SUPABASE_URL') . '/auth/v1/admin/users/' . $user->supabase_id, [
                    'password' => $request->password
                ]);
            }

            $user->save();

            return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update profile: ' . $e->getMessage()], 500);
        }

        return ApiResponseClass::sendSuccess();
    }
}
