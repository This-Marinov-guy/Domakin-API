<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Models\Viewing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;


class ViewingController extends Controller
{
    public function list(): JsonResponse
    {
        $viewings = Viewing::all()->toArray();
         //viewings = $query->get()->toArray();

        return ApiResponseClass::sendSuccess($viewings);
    }

    public function details($id)
    {
        $viewing = Viewing::find($id);

        if (!$viewing) {
            return ApiResponseClass::sendError('Viewing not found');
        }

        return ApiResponseClass::sendSuccess($viewing->toArray());
    }
    
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), Viewing::rules(), Viewing::messages());
        
        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        try {
            Viewing::create([
                'name' => $request->get('name'),
                'surname' => $request->get('surname'),
                'phone' => $request->get('phone'),
                'email' => $request->get('email'),
                'city' => $request->get('city'),
                'address' => $request->get('address'),
                'date' => $request->get('date'),
                'time' => $request->get('time'),
                'note' => $request->get('note'),
            ]);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}

