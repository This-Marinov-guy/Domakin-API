<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

class NewsletterController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), Newsletter::rules(), Newsletter::messages());

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($errors->has('email') && $errors->get('email') === [Newsletter::messages()['email.unique']]) {
                return ApiResponseClass::sendSuccess();
            }

            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        try {
            Newsletter::create($request->all());
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}
