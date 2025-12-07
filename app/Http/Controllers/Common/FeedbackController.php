<?php

namespace App\Http\Controllers\Common;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Feedback;
use App\Helpers\Common;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @OA\Tag(name="Feedback")
 */
class FeedbackController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/feedback/list",
     *     summary="List approved feedbacks",
     *     tags={"Feedback"},
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language code (optional)",
     *         required=false,
     *         @OA\Schema(type="string", example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function list(Request $request, Common $commonService): JsonResponse
    {
        $language = $commonService->getPreferredLanguage();

        $cacheKey = $language ? "feedbacks:$language" : "feedbacks:all";

        $approvedFeedbacks = Cache::remember($cacheKey, 60 * 24, function () use ($language) {
            $query = Feedback::where('approved', true);

            if ($language) {
                $query->where('language', $language);
            }

            return $query->orderBy('id', 'desc')->get()->toArray();
        });

        return ApiResponseClass::sendSuccess($approvedFeedbacks);
    }

    /**
     * @OA\Post(
     *     path="/api/feedback/create",
     *     summary="Create a feedback",
     *     tags={"Feedback"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content", "language"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="content", type="string", example="Great service!"),
     *             @OA\Property(property="language", type="string", example="en")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feedback created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message"),
     *             @OA\Property(property="tag", type="string")
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), Feedback::rules(), Feedback::messages());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Feedback::messages());
        }

        try {
            Feedback::create([
                'name' => $request->get('name') ?? 'Anonymous',
                'content' => $request->get('content'),
                'language' => $request->get('language'),
            ]);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }

    public function approve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $feedback = Feedback::find($request->get('id'));

        if (!$feedback) {
            return ApiResponseClass::sendError('Feedback not found');
        }

        $feedback->approved = true;
        $feedback->save();

        return ApiResponseClass::sendSuccess();
    }
}
