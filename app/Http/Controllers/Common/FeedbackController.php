<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FeedbackController extends Controller
{

    function fetchFeedbacks()
    {
        // step 1: get language from query
        // step 2: fetch all feedbacks that have approved = true and match language (if no language skip condition)
        // step 3: return data
    }

    function createFeedback()
    {
        // step 1: get body 
        // step 2: validate data 
        // step 3: create feedback
        // step 4: return status true
    }
}
