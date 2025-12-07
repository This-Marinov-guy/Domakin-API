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
use App\Services\Integrations\SignalIntegrationService;
use App\Services\PropertyService;
use App\Services\Payment\PaymentLinkService;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * @OA\Tag(name="Properties")
 */
class PropertyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/property/list",
     *     summary="Get user's properties",
     *     tags={"Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function fetchUserProperties(Request $request, UserService $user, PropertyService $propertyService): JsonResponse
    {
        $userId = $user->extractIdFromRequest($request);
        $query = Property::where('created_by', $userId)->select('id', 'status', 'referral_code');
        $paginated = $propertyService->paginateProperties($query, $request);
        return ApiResponseClass::sendSuccess($paginated);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/property/list-extended",
     *     summary="Get all properties (Admin only)",
     *     tags={"Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function fetchAllProperties(Request $request, PropertyService $propertyService): JsonResponse
    {
        $query = Property::query();
        $paginated = $propertyService->paginateProperties($query, $request);
        return ApiResponseClass::sendSuccess($paginated);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/property/listing",
     *     summary="List all active properties",
     *     tags={"Properties"},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     * Lists all active properties
     * 
     * @return JsonResponse
     */
    public function show(PropertyService $propertyService): JsonResponse
    {
        $properties = Property::with(['personalData', 'propertyData'])
            ->whereNotNull('release_timestamp')
            ->where('release_timestamp', '<', Carbon::now())
            ->whereIn('status', values: [1, 2, 3])
            // ->select('id')
            ->get()
            ->toArray();

        $properties = $propertyService->parsePropertiesForListing($properties);

        return ApiResponseClass::sendSuccess($properties);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/property/details/{id}",
     *     summary="Get property details",
     *     tags={"Properties"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Property ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     * Fetches a single property
     * 
     * @param Property $property
     * @return JsonResponse
     */
    public function details($id): JsonResponse
    {
        $propertyData = Property::with(['personalData', 'propertyData'])
            ->where('id', $id)
            ->first()
            ->toArray();

        $propertyData = Helpers::splitStringKeys([$propertyData], ['property_data.images']);

        return ApiResponseClass::sendSuccess($propertyData[0]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/property/create",
     *     summary="Create a new property",
     *     tags={"Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="personalData", type="string", description="JSON string of personal data"),
     *                 @OA\Property(property="propertyData", type="string", description="JSON string of property data"),
     *                 @OA\Property(property="referralCode", type="string"),
     *                 @OA\Property(property="interface", type="string", enum={"web", "mobile", "signal"}, example="web", description="Interface source"),
     *                 @OA\Property(property="terms", type="string", description="JSON string of terms"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary"), description="Property images")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Property created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Please fill/fix the required fields!"),
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string"), example={"personalData.email", "propertyData.city"}),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"), example={"account:authentication.errors.email", "account:authentication.errors.required_fields"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message"),
     *             @OA\Property(property="tag", type="string", example="account:authentication.errors.general")
     *         )
     *     )
     * )
     */
    public function create(Request $request, CloudinaryService $cloudinary, GoogleSheetsService $sheetsService, PropertyService $propertyService, UserService $user, PaymentLinkService $paymentLinks, SignalIntegrationService $signalIntegrationService): JsonResponse
    {
        $data = [
            'personalData' => json_decode($request->get('personalData'), true),
            'propertyData' => json_decode($request->get('propertyData'), true),
            'referral_code' => $request->get('referralCode'),
            'interface' => $request->get('interface'),
            'terms' => json_decode($request->get('terms'), true),
            'images' => $request->file('images'),
            'created_by' => $user->extractIdFromRequest($request),
            'last_updated_by' => $user->extractIdFromRequest($request),
        ];

        $validator = Validator::make($data, Property::rules($request));

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Property::messages());
        }

        // save folder name
        $folder =
            substr($data['propertyData']['description']['en'] ?? $data['propertyData']['description'], 0, 10) . '|' . date('Y-m-d H:i:s');

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
                'interface' => $data['interface'] ?? null,
                'created_by' => $data['created_by'],
                'last_updated_by' => $data['last_updated_by'],
            ]);

            PersonalData::create([
                'property_id' => $property->id,
                ...$data['personalData'],
            ]);

            // Create payment link based on rent (EUR), rounded up
            $rent = (float) ($request->input('propertyData.rent') ?? ($data['propertyData']['rent'] ?? 0));
            $paymentLink = null;
            if ($rent > 0) {
                $paymentLink = $paymentLinks->createPropertyFeeLink($rent);
            }

            PropertyData::create([
                'property_id' => $property->id,
                ...$data['propertyData'],
                'payment_link' => $paymentLink,
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

        try {
            $signalIntegrationService->submitProperty($property);
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/property/edit",
     *     summary="Edit a property (Admin only)",
     *     tags={"Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"id", "propertyData"},
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="propertyData", type="string", description="JSON string of property data"),
     *                 @OA\Property(property="referralCode", type="string"),
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="approved", type="boolean", example=true),
     *                 @OA\Property(property="releaseTimestamp", type="string", format="date-time"),
     *                 @OA\Property(property="terms", type="string", description="JSON string of terms"),
     *                 @OA\Property(property="images", type="string", description="Comma-separated image URLs"),
     *                 @OA\Property(property="newImages", type="array", @OA\Items(type="string", format="binary"), description="New images to upload")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Property updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Property not found"),
     *             @OA\Property(property="tag", type="string", example="account:authentication.errors.general")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Please fill/fix the required fields!"),
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     * Edits a property
     */
    public function edit(Request $request, PropertyService $propertyService, UserService $user, PaymentLinkService $paymentLinks, CloudinaryService $cloudinary): JsonResponse
    {
        $data = [
            'propertyData' => json_decode($request->get('propertyData'), true),
            'id' => $request->get('id'),
            'referral_code' => $request->get('referralCode'),
            'status' => $request->get('status'),
            'approved' => $request->get('approved'),
            'release_timestamp' => $request->get('releaseTimestamp'),
            'terms' => json_decode($request->get('terms'), true),
            'newImages' => $request->file('newImages'),
            'last_updated_by' => $user->extractIdFromRequest($request),
        ];

        $property = Property::find(id: $request->get('id'));

        if (!$property) {
            return ApiResponseClass::sendError('Property not found');
        }

        $validator = Validator::make($data, Property::editRules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Property::messages());
        }

        // Process images - handle both existing images string and new uploads
        $finalImages = [];

        // 1. Handle existing images (order updates or removals)
        if (!empty($request->get('images'))) {
            $existingImages = $request->get('images');
            if (is_string($existingImages)) {
                $existingImages = explode(',', $existingImages);
                $existingImages = array_map('trim', $existingImages);
            }
            $finalImages = is_array($existingImages) ? $existingImages : [];
        } elseif (!empty($data['propertyData']['images'])) {
            $existingImages = $data['propertyData']['images'];
            if (is_string($existingImages)) {
                $existingImages = explode(',', $existingImages);
                $existingImages = array_map('trim', $existingImages);
            }
            $finalImages = is_array($existingImages) ? $existingImages : [];
        }

        // 2. Handle new image uploads if present
        if ($request->hasFile('newImages')) {
            // Get existing folder or create new one if needed
            $folder = $property->propertyData->folder ??
                substr($propertyData['title']['en'] ?? ($propertyData['title'] ?? ''), 0, 10) . '|' . date('Y-m-d H:i:s');

            // Upload new images
            $uploadedImages = $cloudinary->multiUpload($data['newImages'], [
                'folder' => "properties/" . $folder,
            ]);

            // Combine with existing images
            $finalImages = array_merge($finalImages, $uploadedImages);

            // Ensure folder is set in property data
            if (empty($property->propertyData->folder)) {
                $data['propertyData']['folder'] = $folder;
            }
        }

        // 3. Update images field with final result
        if (!empty($finalImages)) {
            $data['propertyData']['images'] = implode(', ', $finalImages);
        }

        $data['propertyData'] = $propertyService->stringifyPropertyDataWithTranslations($data['propertyData']);

        // Refresh payment link if rent is present and > 0
        $rent = (float) (($propertyData['rent'] ?? null) ?? ($property->propertyData->rent ?? 0));
        if ($rent > 0) {
            $data['propertyData']['payment_link'] = $paymentLinks->createPropertyFeeLink($rent);
        }

        try {
            $property->status = $data['status'];
            $property->last_updated_by = $user->extractIdFromRequest($request);
            $property->release_timestamp = $data['release_timestamp'];
            $property->referral_code = $data['referral_code'];
            $property->propertyData->update($data['propertyData']);

            $property->save();
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        return ApiResponseClass::sendSuccess(['message' => 'Property updated successfully']);
    }

    // /**
    //  * Update Property

    //  * @return void
    //  */
    // public function update(PropertyRequest $request, Property $property): JsonResponse
    // {
    //     $property->propertyData = $request->propertyData;
    //     $property->personalData = $request->personalData;

    //     $property->save();
    //     return ApiResponseClass::sendSuccess(['message' => 'Property updated successfully']);
    // }

    /**
     * @OA\Delete(
     *     path="/api/v1/property/delete",
     *     summary="Delete a property",
     *     tags={"Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Property ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Property deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function destroy(Property $property): JsonResponse
    {
        //TODO: perhaps something needs to be done to delete images? Not sure if this deletes images, needs testing
        $property->delete();

        return ApiResponseClass::sendSuccess(['message' => 'Property deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/property/payment/create-link",
     *     summary="Create payment link for a property (Admin only)",
     *     tags={"Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", example=1, description="Property ID"),
     *             @OA\Property(property="name", type="string", example="John Doe", description="Optional name for payment link")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment link created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment_link", type="string", example="https://checkout.stripe.com/...")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Property not found"),
     *             @OA\Property(property="tag", type="string", example="account:authentication.errors.general")
     *         )
     *     )
     * )
     * Creates a payment link for a property
     */
    public function createPaymentLink(Request $request, PaymentLinkService $paymentLinks): JsonResponse
    {
        $propertyId = $request->get('id');
        $name = $request->get('name');
        
        $property = Property::with('propertyData')->find($propertyId);
        
        if (!$property) {
            return ApiResponseClass::sendError('Property not found');
        }
        
        $rent = (float)($property->propertyData->rent ?? 0);
        if ($rent <= 0) {
            return ApiResponseClass::sendError('Property rent must be greater than 0');
        }
        
        $address = $property->propertyData->address ?? '';
        $title = $name 
            ? "One time Domakin Comission - {$name} | {$address}"
            : "One time Domakin Comission - {$address}";
        
        try {
            $paymentLink = $paymentLinks->createPropertyFeeLink($rent, $title);
            
            return ApiResponseClass::sendSuccess([
                'payment_link' => $paymentLink
            ]);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }
    }
}
