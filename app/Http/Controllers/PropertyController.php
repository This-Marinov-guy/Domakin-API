<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;


class PropertyController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $data = [
            'personalData' => json_decode($request->get('personalData'), true),
            'propertyData' => json_decode($request->get('propertyData'), true),
            'terms' => json_decode($request->get('terms'), true)
        ];

        $validator = Validator::make($data, Property::rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }
    }
}
