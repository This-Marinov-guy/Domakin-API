<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Feedback;
use App\Rules\CustomValidator;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class FeedbackController extends Controller
{

    public function fetchFeedbacks(Request $request): JsonResponse
    {
        $language = $request->query('language') ?? null;

        $query = Feedback::where('approved', true);

        if ($language) {
            $query->where('language', $language);
        }

        $approvedFeedbacks = $query->get()->toArray();
        
        return response()->json([
            'status' => true,
            'data' => $approvedFeedbacks
        ]);
    }

    public function createFeedback(Request $request): JsonResponse
    {    
        $rules = [
            'content' => 'required|string|max:200|min:10',
        ];

        $validator = Validator::make($request->all(), [
            'data' => [new CustomValidator($rules)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->get('data')[0],
            ], 200);
        }

        try {
            Feedback::create([
            ]);
        } catch (Exception $error) {
            return response()->json([
                'status' => false,
                'message'=> 'Error',
            ]);  
        } 

        return response()->json(data: [
            'status' => true,
        ]);
    }
}
