<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Models\Renting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class RentingController extends Controller
{
    public function create(Request $request, CloudinaryService $cloudinary): JsonResponse
    {
        $data = [
            'property' => $request->get('property'),
            'name' => $request->get('name'),
            'surname' => $request->get('surname'),
            'phone' => $request->get('phone'),
            'email' => $request->get('email'),
            'letter' => $request->file('letter'),
            'note' => $request->get('note'),
            'terms' => json_decode($request->get('terms'), true),
        ];

        $validator = Validator::make($data, Renting::rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $data['letter'] = $cloudinary->singleUpload($data['letter'], [
            'resource_type' => 'raw',
            'folder' => "motivational_letters",
        ]);

        try {
            Renting::create($data);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}
