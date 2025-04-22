<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Classes\ApiResponseClass;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Validator;

class RegisteredUserController extends Controller
{
    /**
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
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, GoogleSheetsService $sheetsService): JsonResponse
    {
        $isSSO = $request->boolean(key: 'isSSO') ?? false;

        if (!$isSSO) {
            $validator = Validator::make($request->all(), User::rules(), User::messages());

            if ($validator->fails()) {
                return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), User::messages());
            }
        }

        // TODO: move to background task
        $referral_code = '';

        do {
            $referral_code = Str::slug($request->name) . '-' . Str::random(6);
        } while (User::where('referral_code', $referral_code)->exists());

        try {
            User::create([
                ...$request->all(),
                'referral_code' => $referral_code
            ]);

            $sheetsService->exportModelToSpreadsheet(
                User::class,
                'Users'
            );
        } catch (\Exception $error) {
            Log::error($error->getMessage());

            if ($isSSO) {
                $sheetsService->exportModelToSpreadsheet(
                    User::class,
                    'Users'
                );
                return ApiResponseClass::sendSuccess(['user_created' => false]);
            } else {
                return ApiResponseClass::sendError();
            }
        }

        return ApiResponseClass::sendSuccess(['user_created' => true]);
    }
}
