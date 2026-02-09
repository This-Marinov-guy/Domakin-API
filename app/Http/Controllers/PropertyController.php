<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Constants\Translations;
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
use App\Jobs\ReformatPropertyDescriptionJob;
use App\Services\Integrations\SignalIntegrationService;
use App\Services\PropertyService;
use App\Services\Payment\PaymentLinkService;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $query = Property::query()->withCount('rentings');
        $paginated = $propertyService->paginateProperties($query, $request);
        return ApiResponseClass::sendSuccess($paginated);
    }

    /**
     * List all active properties (public listing).
     *
     * Returns properties that are released (release_timestamp in the past) and have status
     * 1 (Pending), 2 (Rent), or 3 (Taken). Content is localized via Accept-Language.
     *
     * @OA\Get(
     *     path="/api/v1/property/listing",
     *     summary="List all active properties",
     *     description="Returns a list of all publicly visible properties. Only properties with a past release_timestamp and status Pending (1), Rent (2), or Taken (3) are included. Titles and descriptions are translated according to the Accept-Language header.",
     *     tags={"Properties"},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Preferred language for titles and descriptions (e.g. en, nl). Defaults to 'en' if omitted.",
     *         required=false,
     *         @OA\Schema(type="string", example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="List of properties formatted for public listing",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id", "status", "statusCode", "slug", "price", "title", "city", "location", "description", "main_image", "images", "link"},
     *                     @OA\Property(property="id", type="integer", description="Listing ID (internal property id + 1000)", example=1023),
     *                     @OA\Property(property="status", type="string", description="Human-readable status", enum={"pending", "rent", "taken"}, example="rent"),
     *                     @OA\Property(property="statusCode", type="integer", description="Numeric status (1=Pending, 2=Rent, 3=Taken)", enum={1, 2, 3}, example=2),
     *                     @OA\Property(property="slug", type="string", description="URL-friendly identifier", example="cozy-room-amsterdam-centrum"),
     *                     @OA\Property(property="price", type="string", description="Rent amount", example="850"),
     *                     @OA\Property(property="title", type="string", description="Translated listing title", example="Cozy room in Amsterdam Centrum"),
     *                     @OA\Property(property="city", type="string", description="City name", example="Amsterdam"),
     *                     @OA\Property(property="location", type="string", description="Street and city", example="Herengracht 123, Amsterdam"),
     *                     @OA\Property(
     *                         property="description",
     *                         type="object",
     *                         description="Translated description blocks",
     *                         @OA\Property(property="property", type="string", example="Spacious room with canal view, 15m². Shared kitchen and bathroom."),
     *                         @OA\Property(property="period", type="string", example="From 1 March 2025, minimum 6 months"),
     *                         @OA\Property(property="bills", type="string", example="Included: gas, water, electricity, internet"),
     *                         @OA\Property(property="flatmates", type="string", example="2 flatmates, international household")
     *                     ),
     *                     @OA\Property(property="main_image", type="string", nullable=true, description="URL of the primary image", example="https://res.cloudinary.com/example/image/upload/v123/room1.jpg"),
     *                     @OA\Property(property="images", type="array", description="Additional image URLs", @OA\Items(type="string"), example={"https://res.cloudinary.com/example/image/upload/v123/room2.jpg", "https://res.cloudinary.com/example/image/upload/v123/room3.jpg"}),
     *                     @OA\Property(property="link", type="string", description="Public property page path (locale-prefixed when not en). Use with app base URL.", example="https://www.domakin.nl/en/services/renting/property/1023-cozy-room-amsterdam-centrum")
     *                 )
     *             ),
     *             example={
     *                 "status": true,
     *                 "data": {
     *                     {
     *                         "id": 1023,
     *                         "status": "rent",
     *                         "statusCode": 2,
     *                         "slug": "cozy-room-amsterdam-centrum",
     *                         "price": "850",
     *                         "title": "Cozy room in Amsterdam Centrum",
     *                         "city": "Amsterdam",
     *                         "location": "Herengracht 123, Amsterdam",
     *                         "description": {
     *                             "property": "Spacious room with canal view, 15m². Shared kitchen and bathroom.",
     *                             "period": "From 1 March 2025, minimum 6 months",
     *                             "bills": "Included: gas, water, electricity, internet",
     *                             "flatmates": "2 flatmates, international household"
     *                         },
     *                         "main_image": "https://res.cloudinary.com/example/image/upload/v123/room1.jpg",
     *                         "images": {"https://res.cloudinary.com/example/image/upload/v123/room2.jpg", "https://res.cloudinary.com/example/image/upload/v123/room3.jpg"},
     *                         "link": "https://www.domakin.nl/en/services/renting/property/1023-cozy-room-amsterdam-centrum"
     *                     },
     *                     {
     *                         "id": 1024,
     *                         "status": "pending",
     *                         "statusCode": 1,
     *                         "slug": "bright-studio-jordaan",
     *                         "price": "1200",
     *                         "title": "Bright studio in the Jordaan",
     *                         "city": "Amsterdam",
     *                         "location": "Egelantiersgracht 45, Amsterdam",
     *                         "description": {
     *                             "property": "Furnished studio, 25m². Private kitchen and bathroom.",
     *                             "period": "Available from 15 April 2025",
     *                             "bills": "Excl. utilities, approx. €80/month",
     *                             "flatmates": "No flatmates, self-contained"
     *                         },
     *                         "main_image": "https://res.cloudinary.com/example/image/upload/v123/studio1.jpg",
     *                         "images": {},
     *                         "link": "https://www.domakin.nl/bg/services/renting/property/bright-studio-jordaan"
     *                     }
     *                 }
     *             }
     *         )
     *     )
     * )
     *
     * @return JsonResponse
     */
    public function show(Request $request, PropertyService $propertyService): JsonResponse
    {
        $properties = Property::with(['personalData', 'propertyData'])
            ->whereNotNull('release_timestamp')
            ->where('release_timestamp', '<', Carbon::now())
            ->whereIn('status', values: [1, 2, 3])
            // ->select('id')
            ->get()
            ->toArray();

        // Extract language from Accept-Language header
        $language = $this->extractLanguageFromRequest($request);

        $properties = $propertyService->parsePropertiesForListing(properties: $properties, language: $language);

        return ApiResponseClass::sendSuccess($properties);
    }

    /**
     * List all active properties with status "Rent" (2) as XML.
     * Same filters as listing (released, status 2 only) for feeds/sitemaps.
     */
    public function listingXml(Request $request, PropertyService $propertyService): Response
    {
        $properties = Property::with(['personalData', 'propertyData'])
            ->whereNotNull('release_timestamp')
            ->where('release_timestamp', '<', Carbon::now())
            ->where('status', 2)
            ->get()
            ->toArray();

        $language = $this->extractLanguageFromRequest($request);
        $properties = $propertyService->parsePropertiesForListing(properties: $properties, language: $language);

        $xml = $this->buildPropertiesXml($properties);

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Build XML string from listing properties array.
     *
     * @param array<int, array<string, mixed>> $properties
     * @return string
     */
    private function buildPropertiesXml(array $properties): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('properties');
        $writer->writeAttribute('count', (string) count($properties));

        foreach ($properties as $p) {
            $writer->startElement('property');
            $writer->writeElement('id', (string) ($p['id'] ?? ''));
            $writer->writeElement('status', (string) ($p['status'] ?? ''));
            $writer->writeElement('statusCode', (string) ($p['statusCode'] ?? ''));
            $writer->writeElement('slug', (string) ($p['slug'] ?? ''));
            $writer->writeElement('price', (string) ($p['price'] ?? ''));
            $writer->writeElement('title', (string) ($p['title'] ?? ''));
            $writer->writeElement('city', (string) ($p['city'] ?? ''));
            $writer->writeElement('location', (string) ($p['location'] ?? ''));
            $writer->writeElement('link', (string) ($p['link'] ?? ''));
            $writer->writeElement('main_image', (string) ($p['main_image'] ?? ''));

            if (!empty($p['description']) && is_array($p['description'])) {
                $writer->startElement('description');
                foreach ($p['description'] as $key => $value) {
                    $writer->writeElement($key, (string) $value);
                }
                $writer->endElement();
            }

            if (!empty($p['images']) && is_array($p['images'])) {
                $writer->startElement('images');
                foreach ($p['images'] as $url) {
                    $writer->writeElement('image', (string) $url);
                }
                $writer->endElement();
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Extract language from Accept-Language header
     *
     * @param Request $request
     * @return string
     */
    private function extractLanguageFromRequest(Request $request): string
    {
        $acceptLanguage = $request->header('Accept-Language', '');
        
        if (empty($acceptLanguage)) {
            return 'en'; // Default to English
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,nl;q=0.8")
        // Extract the first language code
        if (preg_match('/^([a-z]{2})(?:-[a-z]{2})?(?:;q=[\d.]+)?/i', $acceptLanguage, $matches)) {
            $language = strtolower($matches[1]);
            
            // Validate against supported locales
            $supportedLocales = Translations::WEB_SUPPORTED_LOCALES;
            if (in_array($language, $supportedLocales)) {
                return $language;
            }
        }

        return 'en'; // Default fallback
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
    public function create(Request $request, CloudinaryService $cloudinary, GoogleSheetsService $sheetsService, PropertyService $propertyService, UserService $user, PaymentLinkService $paymentLinks): JsonResponse
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
            
            if ($rent > 0) {string: 
                $mainImage = explode(',', $data['propertyData']['images'])[0];
                $paymentLink = $paymentLinks->createPropertyFeeLink($rent, imageSrc: $mainImage);
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
            // Dispatch background job to reformat and translate description using OpenAI
            ReformatPropertyDescriptionJob::dispatch($property->id);
        } catch (Exception $error) {
            Log::error($error->getMessage());
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
    public function edit(Request $request, PropertyService $propertyService, UserService $user, PaymentLinkService $paymentLinks, CloudinaryService $cloudinary, SignalIntegrationService $signalIntegrationService): JsonResponse
    {
        // Normalize releaseTimestamp - convert string "null" to actual null
        $releaseTimestamp = $request->get('releaseTimestamp');

        if ($releaseTimestamp === 'null' || $releaseTimestamp === null || $releaseTimestamp === '') {
            $releaseTimestamp = null;
        }

        $referralCode = $request->get('referralCode');

        if ($referralCode === 'null' || $referralCode === null || $referralCode === '') {
            $referralCode = null;
        }

        $data = [
            'propertyData' => json_decode($request->get('propertyData'), true),
            'id' => $request->get('id'),
            'referral_code' => $referralCode,
            'status' => $request->get('status'),
            'approved' => $request->get('approved'),
            'release_timestamp' => $releaseTimestamp,
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
            $mainImage = explode(',', $data['propertyData']['images'])[0];
            $data['propertyData']['payment_link'] = $paymentLinks->createPropertyFeeLink($rent, imageSrc: $mainImage);
        }

        $signalFlag = $request->boolean('is_signal', default: false);

        $signalRequestSuccess = true;

        if ($signalFlag !== null && $property->is_signal !== $signalFlag) {
            try {
                $signalFlag ?
                    $signalIntegrationService->submitProperty($property) :
                    $signalIntegrationService->deleteProperty($property);

                $property->is_signal = $signalFlag;
            } catch (Exception $error) {
                Log::error($error->getMessage());
                $signalRequestSuccess = false;
            }
        }

        try {
            $property->status = $data['status'];
            $property->last_updated_by = $user->extractIdFromRequest($request);
            $property->referral_code = $data['referral_code'];
            
            // Handle release_timestamp - set to null if explicitly null, otherwise set the value
            if ($data['release_timestamp'] === null) {
                $property->release_timestamp = null;
            } else {
                $property->release_timestamp = $data['release_timestamp'];
            }
            
            $property->propertyData->update($data['propertyData']);

            $property->save();
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        return ApiResponseClass::sendSuccess(['message' => 'Property updated successfully', 'warning' => $signalRequestSuccess ? null : 'Signal request failed']);
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
    public function delete(Request $request, SignalIntegrationService $signalIntegrationService): JsonResponse
    {
        $propertyId = $request->get('id');
        $property = Property::find($propertyId);

        if (!$property) {
            return ApiResponseClass::sendError('Property not found');
        }

        // Delete from Signal API if property exists
        try {
            $signalIntegrationService->deleteProperty($property);
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        //TODO: perhaps something needs to be done to delete images? Not sure if this deletes images, needs testing
        $property->delete();

        return ApiResponseClass::sendSuccess(['message' => 'Property deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/property/signal-test",
     *     summary="Test Signal integration with the latest property",
     *     tags={"Properties"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Signal integration test completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Property not found"),
     *             @OA\Property(property="tag", type="string", example="property_not_found")
     *         )
     *     )
     * )
     */
    public function testSignalIntegration(SignalIntegrationService $signalIntegrationService): JsonResponse
    {
        $property = Property::with('propertyData')->latest()->first();

        if (!$property) {
            return ApiResponseClass::sendError('Property not found', 'property_not_found');
        }

        $createResponse = null;
        $deleteResponse = null;

        try {
            $createResponse = $signalIntegrationService->submitProperty($property);
        } catch (Exception $error) {
            Log::error('Signal create test failed: ' . $error->getMessage());
        }

        try {
            $deleteResponse = $signalIntegrationService->deleteProperty($property);
        } catch (Exception $error) {
            Log::error('Signal delete test failed: ' . $error->getMessage());
        }

        return ApiResponseClass::sendSuccess([
            'message' => 'Signal integration test completed',
            'create_response' => $createResponse,
            'delete_response' => $deleteResponse,
        ]);
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
            $mainImage = explode(',', $property->propertyData->images)[0];
            $paymentLink = $paymentLinks->createPropertyFeeLink($rent, $title, imageSrc: $mainImage);

            return ApiResponseClass::sendSuccess([
                'payment_link' => $paymentLink
            ]);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }
    }
}
