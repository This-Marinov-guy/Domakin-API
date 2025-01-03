<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Models\Property;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;


class PropertyController extends Controller
{
    public function create(Request $request, CloudinaryService $cloudinary): JsonResponse
    {
        $data = [
            'personalData' => json_decode($request->get('personalData'), true),
            'propertyData' => json_decode($request->get('propertyData'), true),
            'terms' => json_decode($request->get('terms'), true),
            'images' => $request->file('images')
        ];

        $validator = Validator::make($data, Property::rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        // save folder name
        $folder =
        substr($data['propertyData']['description'], 0, 10) . '|' . date('Y-m-d H:i:s');

        // translate the data later
        $data['propertyData']['period'] = [
            'en' => $data['propertyData']['period']
        ];

        $data['propertyData']['rent'] = [
            'en' => $data['propertyData']['rent']
        ];

        $data['propertyData']['bills'] = [
            'en' => $data['propertyData']['bills']
        ];

        $data['propertyData']['flatmates'] = [
            'en' => $data['propertyData']['flatmates']
        ];

        $data['propertyData']['description'] = [
            'en' => $data['propertyData']['description']
        ];

        $data['propertyData']['folder'] = $folder;

        try {
            // upload images
            $data['propertyData']['images'] = $cloudinary->multiUpload($data['images'], [
                'folder' => "properties/" . $folder,
            ]);

            Property::create([
                'personal_data' => $data['personalData'],
                'property_data' => $data['propertyData']
            ]);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
            (new Notification('New property uploaded', 'property', $data))->sendNotification();
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}
