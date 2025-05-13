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

class FeedbackController extends Controller
{

    public function list(Request $request, Common $commonService): JsonResponse
    {
        $language = $commonService->getPreferredLanguage();

        $cacheKey = $language ? "feedbacks:$language" : "feedbacks:all";

        $approvedFeedbacks = Cache::remember($cacheKey, 60 * 24, function () use ($language) {
            $query = Feedback::where('approved', true);

            if ($language) {
                $query->where('language', $language);
            }

            return $query->get()->toArray();
        });

        return ApiResponseClass::sendSuccess($approvedFeedbacks);
    }

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
