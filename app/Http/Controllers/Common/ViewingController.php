<?php

namespace App\Http\Controllers\Common;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Models\Viewing;
// use Illuminate\Support\Facades\Validator;
// use App\Models\Feedback;
// use Exception;
// use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ViewingController extends Controller
{
    public function list(): JsonResponse
    {
        $viewings = Viewing::all()->toArray();
         //viewings = $query->get()->toArray();

        return ApiResponseClass::sendSuccess($viewings);
    }

    public function details()
    {
        //
    }
    
    public function create()
    {
        //
    }
}

