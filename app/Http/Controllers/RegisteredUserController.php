<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Classes\ApiResponseClass;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Authentication")
 */
class RegisteredUserController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/authentication/validate-credentials",
     *     summary="Validate user credentials before registration",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credentials are valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Please fill/fix the required fields!"),
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), User::rules(), User::messages());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), User::messages());
        }

        return ApiResponseClass::sendSuccess();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/authentication/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password", "name", "surname"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="name", type="string", example="John"),
     *             @OA\Property(property="surname", type="string", example="Doe"),
     *             @OA\Property(property="phone", type="string", example="+31 6 12345678"),
     *             @OA\Property(property="isSSO", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User registered successfully",
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
     *             @OA\Property(property="message", type="string", example="Please fill/fix the required fields!"),
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string"), example={"email", "password"}),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"), example={"account:authentication.errors.email", "account:authentication.errors.password"})
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
     *     )
     * )
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, GoogleSheetsService $sheetsService): JsonResponse
    {
        $isSSO = $request->boolean(key: 'isSSO') ?? false;
        $email = $request->get('email');

        if (!$isSSO) {
            $validator = Validator::make($request->all(), User::rules(), User::messages());

            if ($validator->fails()) {
                return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), User::messages());
            }
        }

        // SSO returning user — already in users table, nothing to create
        if ($isSSO && User::where('email', $email)->exists()) {
            return ApiResponseClass::sendSuccess(['user_created' => false]);
        }

        // Trim and split CamelCase (e.g. EluminaVision → Elumina Vision) for name parts
        $name = static::normalizeNamePart((string) ($request->get('name') ?? ''));
        $surname = static::normalizeNamePart((string) ($request->get('surname') ?? ''));

        $profileImage = trim((string) ($request->get('profile_image') ?? '')) ?: '/assets/img/dashboard/avatar_0' . mt_rand(1, 5) . '.jpg';

        // TODO: move to background task
        $referral_code = '';

        do {
            $referral_code = Str::slug($name) . '-' . Str::random(6);
        } while (User::where('referral_code', $referral_code)->exists());

        // Resolve id from auth.users by email so public.users and auth.users id stay in sync
        $userId = null;
        try {
            $authUser = DB::connection('pgsql')
                ->table('auth.users')
                ->where('email', $email)
                ->first();

            if ($authUser && isset($authUser->id)) {
                $userId = $authUser->id;
            }
        } catch (\Exception $e) {
            Log::warning('Register: could not read auth.users: ' . $e->getMessage());
        }

        if ($userId === null) {
            Log::warning('Register: no auth.users row found for ' . $email . ' — user must be created in Supabase Auth first.');
            return ApiResponseClass::sendError('User not found in authentication. Please sign up via the app first.', 400);
        }

        try {
            User::create([
                'id' => $userId,
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'profile_image' => $profileImage,
                'phone' => $request->get('phone'),
                'password' => $request->get('password'),
                'referral_code' => $referral_code,
            ]);

            $sheetsService->exportModelToSpreadsheet(User::class, 'Users');
        } catch (\Exception $error) {
            Log::error($error->getMessage());
            return ApiResponseClass::sendError();
        }

        return ApiResponseClass::sendSuccess(['user_created' => true]);
    }

    /**
     * Trim and split CamelCase: "EluminaVision" → "Elumina Vision".
     */
    private static function normalizeNamePart(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return trim(preg_replace('/([a-z])([A-Z])/', '$1 $2', $value));
    }
}
