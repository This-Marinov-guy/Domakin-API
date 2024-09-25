<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Models\Feedback;
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

    public function createFeedback(StorePostRequest $request): JsonResponse
    {
        // step 1: get body 


        // step 2: validate data 
        //DONE
        
        // step 3: create feedback
        $feedback = Feedback::create();

        // step 4: return status true

        return response()->json([
            'status'=> true,
            'feedback'=> $feedback,
        ]);
    }
}
