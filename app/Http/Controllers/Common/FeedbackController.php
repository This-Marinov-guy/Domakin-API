<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Feedback;
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
        $validator = Validator::make($request->all(), Feedback::rules(), Feedback::messages());

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'invalid_fields' => $validator->errors()->toArray(),
            ]);  
        }

        try {
            Feedback::create([
                'name' => $request->get('name') ?? 'Anonymous',
                'content' => $request->get('content'),
                'language' => $request->get('language'), 
            ]);
        } catch (Exception $error) {
            return response()->json([
                'status' => false,
                'message'=> 'Something went wrong',
            ]);  
        } 

        return response()->json(data: [
            'status' => true,
        ]);
    }
}
