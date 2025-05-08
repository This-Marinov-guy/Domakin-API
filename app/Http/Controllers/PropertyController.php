<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use Illuminate\Support\Carbon;
use App\Models\Property;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Http\Requests\PropertyRequest;
use App\Models\PersonalData;
use App\Models\PropertyData;
use App\Services\Helpers;
use App\Services\PropertyService;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class PropertyController extends Controller
{
    public function fetchUserProperties(Request $request, UserService $user): JsonResponse
    {
        $userId = $user->extractIdFromRequest($request);

        $properties = Property::with(['personalData', 'propertyData'])
            ->where('created_by', $userId)
            ->select('id', 'status')
            ->get();

        return ApiResponseClass::sendSuccess($properties);
    }

    public function fetchAllProperties(): JsonResponse
    {
        $properties = Property::with(['personalData', 'propertyData'])
            ->get();

        return ApiResponseClass::sendSuccess($properties);
    }

    /**
     * Lists all active properties
     * 
     * @return JsonResponse
     */
    public function show(PropertyService $propertyService): JsonResponse
    {
        $properties = Property::with(['personalData', 'propertyData'])
            ->whereNotNull('release_timestamp')
            ->where('release_timestamp', '<', Carbon::now())
            ->where('status', 2)
            // ->select('id')
            ->get()
            ->toArray();

        $properties = $propertyService->parsePropertiesForListing($properties);

        return ApiResponseClass::sendSuccess($properties);
    }

    public function create(Request $request, CloudinaryService $cloudinary, GoogleSheetsService $sheetsService, PropertyService $propertyService, UserService $user): JsonResponse
    {
        $data = [
            'personalData' => json_decode($request->get('personalData'), true),
            'propertyData' => json_decode($request->get('propertyData'), true),
            'referral_code' => $request->get('referralCode'),
            'terms' => json_decode($request->get('terms'), true),
            'images' => $request->file('images'),
            'created_by' => $user->extractIdFromRequest($request),
            'last_updated_by' => $user->extractIdFromRequest($request),
        ];

        $validator = Validator::make($data, Property::rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Property::messages());
        }

        // save folder name
        $folder =
            substr($data['propertyData']['description'], 0, 10) . '|' . date('Y-m-d H:i:s');

        // modify property data with translations
        $data['propertyData'] = $propertyService->modifyPropertyDataWithTranslations($data['propertyData']);

        $data['propertyData']['folder'] = $folder;

        try {
            // upload images
            $data['propertyData']['images'] = $cloudinary->multiUpload($data['images'], [
                'folder' => "properties/" . $folder,
            ]);
            $data['propertyData']['images'] = implode(", ", $data['propertyData']['images']);

            $property = Property::create([
                'referral_code' => $data['referral_code'],
                'created_by' => $data['created_by'],
                'last_updated_by' => $data['last_updated_by'],
            ]);

            PersonalData::create([
                'property_id' => $property->id,
                ...$data['personalData'],
            ]);

            PropertyData::create([
                'property_id' => $property->id,
                ...$data['propertyData'],
            ]);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
            (new Notification('New property uploaded', 'property', $data))->sendNotification();

            $sheetsService->exportModelToSpreadsheet(
                Property::class,
                'Properties'
            );
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }

    /**
     * Update Property
   
     * @return void
     */
    public function update(PropertyRequest $request, Property $property): JsonResponse
    {
        $property->propertyData = $request->propertyData;
        $property->personalData = $request->personalData;

        $property->save();
        return ApiResponseClass::sendSuccess(['message' => 'Property updated successfully']);
    }

    public function destroy(Property $property): JsonResponse
    {
        //TODO: perhaps something needs to be done to delete images? Not sure if this deletes images, needs testing
        $property->delete();

        return ApiResponseClass::sendSuccess(['message' => 'Property deleted successfully']);
    }
}
