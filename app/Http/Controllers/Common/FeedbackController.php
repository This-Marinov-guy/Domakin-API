<?php

namespace App\Http\Controllers\Common;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Feedback;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class FeedbackController extends Controller
{

    public function list(Request $request): JsonResponse
    {
        $language = $request->query('language') ?? null;

        $query = Feedback::where('approved', true);

        if ($language) {
            $query->where('language', $language);
        }

        $approvedFeedbacks = $query->get()->toArray();

        return ApiResponseClass::sendSuccess($approvedFeedbacks);
    }

    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), Feedback::rules(), Feedback::messages());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
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
