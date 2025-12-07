<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Support\Facades\Validator;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @OA\Tag(name="Newsletter")
 */
class NewsletterController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/common/newsletter/subscribe",
     *     summary="Subscribe to newsletter",
     *     tags={"Newsletter"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscribed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function create(Request $request, GoogleSheetsService $sheetsService): JsonResponse
    {
        $validator = Validator::make($request->all(), Newsletter::rules(), Newsletter::messages());

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($errors->has('email') && $errors->get('email') === [Newsletter::messages()['email.unique']]) {
                return ApiResponseClass::sendSuccess();
            }

            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Newsletter::messages());
        }

        try {
            Newsletter::create($request->all());
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
            $sheetsService->exportModelToSpreadsheet(
                Newsletter::class,
                'Newsletter emails'
            );
        } catch (Exception $error) {
            //do nothing        
        }

        return ApiResponseClass::sendSuccess();
    }

    /**
     * @OA\Delete(
     *     path="/api/common/newsletter/unsubscribe",
     *     summary="Unsubscribe from newsletter",
     *     tags={"Newsletter"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "id"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Unsubscribed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, GoogleSheetsService $sheetsService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        try {
            Newsletter::where('id', $request->id)->where('email', $request->email)->delete();
        } catch (Exception $error) {
            return ApiResponseClass::sendError("No mail found!");
        }

        try {
            $sheetsService->exportModelToSpreadsheet(
                Newsletter::class,
                'Newsletter emails'
            );
        } catch (Exception $error) {
            //do nothing        
        }

        return ApiResponseClass::sendSuccess();
    }
}
