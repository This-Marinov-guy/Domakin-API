<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use Illuminate\Support\Carbon;
use App\Models\Property;
<<<<<<< HEAD
use App\Models\PersonalData;
use App\Models\PropertyData;
=======
use App\Services\UserService;
>>>>>>> main
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
            ->get()
            ->toArray();

        $modifiedProperties = Helpers::splitStringKeys($properties, ['property_data.images']);

        return ApiResponseClass::sendSuccess($modifiedProperties);
    }

    public function fetchAllProperties(): JsonResponse
    {
        $properties = Property::with(['personalData', 'propertyData'])
            ->get()
            ->toArray();

        $modifiedProperties = Helpers::splitStringKeys($properties, ['property_data.images']);

        return ApiResponseClass::sendSuccess($modifiedProperties);
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
            // ->select('id')
            ->get()
            ->toArray();

        $properties = $propertyService->parsePropertiesForListing($properties);

        return ApiResponseClass::sendSuccess($properties);
    }

    public function create(Request $request, CloudinaryService $cloudinary, GoogleSheetsService $sheetsService, PropertyService $propertyService, UserService $user): JsonResponse
    {
<<<<<<< HEAD
        // $data = [
        //     'personalData' => json_decode($request->get('personalData'), true),
        //     'propertyData' => json_decode($request->get('propertyData'), true),
        //     'terms' => json_decode($request->get('terms'), true),
        //     'images' => $request->file('images')
        // ];

        $personalData = $request->input('personalData');
        $propertyData = $request->input('propertyData');
        $terms = $request->input('terms');
        $images = $request->file('images');

        //$validator = Validator::make($data, PropertyRequest::rules());

        // if ($validator->fails()) {
        //     return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        // }

        // save folder name
        $folder =
        substr($propertyData['description'], 0, 10) . '|' . date('Y-m-d H:i:s');

        // translate the data later
        $propertyData['period'] = [
            'en' => $propertyData['period'] 
        ];

        $propertyData['rent'] = [
            'en' => $propertyData['rent']
        ];

        $propertyData['bills'] = [
            'en' => $propertyData['bills']
        ];

        $propertyData['flatmates'] = [
            'en' => $propertyData['flatmates']
        ];

        $propertyData['description'] = [
            'en' => $propertyData['description']	
        ];
=======
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
>>>>>>> main

            $propertyData['folder'] = $folder;
            
        
        try {
            //upload images TMPORARILY COMMENTED OUT
            // $images['images'] = $cloudinary->multiUpload($images, [
            //     'folder' => "properties/" . $folder,
            // ]);


            if ($images) {
                $uploadedImages = $cloudinary->multiUpload($images, [
                    'folder' => "properties/" . $folder,
                ]);
                $propertyData['images'] = $uploadedImages;
            } else {
                $propertyData['images'] = [];
            }


            // Property::create([
            //     'personal_data' => $data['personalData'],
            //     'property_data' => $data['propertyData']
            // ]);

            
//TODO: Currentlygetting this response from Postman: Array to string conversion (Connection: pgsql, SQL: insert into "property_data" ("city", "address", "size", "period", "rent", "bills", "flatmates", "registration", "description", "properties_id", "updated_at", "created_at") values (Anytown, 123 Main St, 100 sqm, ?, ?, ?, ?, yes, ?, 5, 2025-02-18 17:27:17, 2025-02-18 17:27:17) returning "id")
//Fix it
            //saves property and personal data to separate tables after validation
            // $property = Property::create($personalData);
            $property = Property::create([
                'personal_data' => json_encode($personalData),
                'property_data' => json_encode($propertyData)
            ]);
            $data['propertyData']['images'] = implode(", ", $data['propertyData']['images']);

<<<<<<< HEAD
            $personalDataModel = new PersonalData($personalData);
            $property->personalData()->save($personalDataModel);

            // $propertyDataModel = new PropertyData($propertyData);
            $propertyDataModel = new PropertyData([
            'city' => $propertyData['city'],
            'address' => $propertyData['address'],
            'size' => $propertyData['size'],
            'period' => json_encode($propertyData['period']),
            'rent' => json_encode($propertyData['rent']),
            'bills' => json_encode($propertyData['bills']),
            'flatmates' => json_encode($propertyData['flatmates']),
            'registration' => $propertyData['registration'],
            'description' => json_encode($propertyData['description']),
            'images' => json_encode($propertyData['images']),
            'properties_id' => $property->id,
=======
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
>>>>>>> main
            ]);
            $property->propertyData()->save($propertyDataModel);

        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
<<<<<<< HEAD
            (new Notification('New property uploaded', 'property', $request->all))->sendNotification();
=======
            (new Notification('New property uploaded', 'property', $data))->sendNotification();

            $sheetsService->exportModelToSpreadsheet(
                Property::class,
                'Properties'
            );
>>>>>>> main
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }

    /**
<<<<<<< HEAD
     * Lists all properties in Properties Table
     * 
     * @return JsonResponse
     */
    public function show () : JsonResponse
    {
        $properties = Property::all();

        return ApiResponseClass::sendSuccess($properties);
    }


    /**
=======
>>>>>>> main
     * Update Property
   
     * @param PropertyRequest $request
     * @return void
     */
<<<<<<< HEAD
    public function update (PropertyRequest $request, Property $property, CloudinaryService $cloudinary, $id) : JsonResponse
    {
        $property = Property::findOrFail($id);

        $images = $request->file('images');

         // Update property data
         $propertyData = $request->input('propertyData');
         $personalData = $request->input('personalData');
    
         $property->property_data = json_encode($propertyData);
         $property->personal_data = json_encode($personalData);
         $property->save();

        $folder =
        substr($propertyData['description'], 0, 10) . '|' . date('Y-m-d H:i:s');

        $propertyData['folder'] = $folder;
            
        try {

            if ($images) {
                $uploadedImages = $cloudinary->multiUpload($images, [
                    'folder' => "properties/" . $folder,
                ]);
                $propertyData['images'] = $uploadedImages;
            } else {
                $propertyData['images'] = [];
            }

            // Update related PersonalData
            $personalDataModel = $property->personalData;
            if ($personalDataModel)
            {
                $personalDataModel->update($personalData);
            } 
            else
            {
                $personalDataModel = new PersonalData($personalData);
                $property->personalData()->save($personalDataModel);
            }

            if ($images) {

                $propertyData['images'] = [];
            }

            // Update related PropertyData
            $propertyDataModel = $property->propertyData;
            if ($propertyDataModel) {
                $propertyDataModel->update([
                    'city' => $propertyData['city'],
                    'address' => $propertyData['address'],
                    'size' => $propertyData['size'],
                    'period' => json_encode($propertyData['period']),
                    'rent' => json_encode($propertyData['rent']),
                    'bills' => json_encode($propertyData['bills']),
                    'flatmates' => json_encode($propertyData['flatmates']),
                    'registration' => $propertyData['registration'],
                    'description' => json_encode($propertyData['description']),
                    'images' => json_encode($propertyData['images']),
                ]);
            } else {
                return ApiResponseClass::sendError('Property not found');
            }
=======
    public function update(PropertyRequest $request, Property $property): JsonResponse
    {
        $property->propertyData = $request->propertyData;
        $property->personalData = $request->personalData;
>>>>>>> main

        return ApiResponseClass::sendSuccess(['message' => 'Property updated successfully']);
            
        }
        catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }
    }

<<<<<<< HEAD
    /**
     * Delete Property
     * 
     * @param int $id
     * @param Property $property
     * @return JsonResponse
     */
    public function destroy ($id, Property $property) : JsonResponse
=======
    public function destroy(Property $property): JsonResponse
>>>>>>> main
    {
        //TODO: perhaps something needs to be done to delete images? Not sure if this deletes images, needs testing
        $property = Property::findOrFail($id);
        $property->delete();

        return ApiResponseClass::sendSuccess(['message' => 'Property deleted successfully']);
    }
}
