<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
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
        // step 1: get body 

        // step 2: validate data 
    
        $validatedDate= $request->validated([
            'required|string|max:200|min:10',
        ]);


        // step 3: create feedback
        $feedback = null;

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
