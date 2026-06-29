<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Constants\Properties;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Jobs\ExportModelToSpreadsheetJob;
use App\Jobs\SendInternalNotificationJob;
use App\Jobs\SendSearchRentingMailerJob;
use App\Models\Property;
use App\Models\Renting;
use App\Models\SearchRenting;
use App\Services\SearchRentingMailerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\GoogleServices\GoogleSheetsService;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class SearchRentingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/renting/searching/create",
     *     summary="Submit a property search request",
     *     tags={"Renting"},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Website locale used for request tracking",
     *         @OA\Schema(type="string", example="bg")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "surname", "phone", "email", "people", "type", "moveIn", "period", "registration", "budget", "city", "interface"},
     *                 @OA\Property(property="name", type="string", example="John"),
     *                 @OA\Property(property="surname", type="string", example="Doe"),
     *                 @OA\Property(property="phone", type="string", example="+31 6 12345678"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="people", type="integer", example=2),
     *                 @OA\Property(property="type", type="string", example="room"),
     *                 @OA\Property(property="moveIn", type="string", format="date", example="2025-12-01"),
     *                 @OA\Property(property="period", type="string", example="12 months"),
     *                 @OA\Property(property="registration", type="boolean", example=true),
     *                 @OA\Property(property="budget", type="number", example=1200),
     *                 @OA\Property(property="city", type="string", example="Amsterdam"),
     *                 @OA\Property(property="property_id", type="integer", nullable=true, example=42, description="Optional related property ID from the properties table"),
     *                 @OA\Property(property="note", type="string"),
     *                 @OA\Property(property="referralCode", type="string"),
     *                 @OA\Property(property="letter", type="string", format="binary", description="Optional motivational letter"),
     *                 @OA\Property(property="interface", type="string", enum={"web", "mobile", "signal"}, example="web", description="Interface source")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search request submitted successfully",
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
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string"), example={"email", "phone"}),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"), example={"account:authentication.errors.email", "account:authentication.errors.phone_invalid"})
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
    public function create(Request $request, CloudinaryService $cloudinary, GoogleSheetsService $sheetsService): JsonResponse
    {
        $rawPropertyId = $request->get('property_id') ?? $request->get('propertyId');
        $propertyId = null;
        $property = null;

        if ($rawPropertyId !== null && $rawPropertyId !== '') {
            $propertyId = (int) $rawPropertyId;

            if ($propertyId >= Properties::FRONTEND_PROPERTY_ID_INDEXING) {
                $propertyId -= Properties::FRONTEND_PROPERTY_ID_INDEXING;
            }

            if ($propertyId > 0) {
                $property = Property::with('propertyData')->find($propertyId);
            }
        }

        $data = [
            'property_id' => $propertyId,
            'name' => $request->get('name'),
            'surname' => $request->get('surname'),
            'phone' => $request->get('phone'),
            'email' => $request->get('email'),
            'people' => $request->get('people'),
            'type' => $request->get('type'),
            'move_in' => $request->get('moveIn'),
            'period' => $request->get('period'),
            'registration' => $request->get('registration'),
            'budget' => $request->get('budget'),
            'city' => $request->get('city'),
            'note' => $request->get('note'),
            'referral_code' => $request->get('referralCode'),
            'locale' => $this->requestLocale($request),
            'interface' => $request->get('interface'),
            'letter' => $request->file('letter') ?? null,
            'terms' => json_decode($request->get('terms'), true),
        ];

        $validator = Validator::make($data, SearchRenting::rules($request));

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), SearchRenting::messages()); 
        }

        if ($data['letter']) {
            $data['letter'] = $cloudinary->singleUpload($data['letter'], [
                'public_id' => uniqid() . '.' .  $request->file('letter')->getClientOriginalExtension(),
                'resource_type' => 'raw',
                'folder' => "motivational_letters",
            ]);
        }

        try {
            $searchRenting = SearchRenting::create($data);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
            $notificationData = $data;
            $notificationSubject = 'New searching for Renting';
            $notificationTemplate = 'search_renting';

            if ($property) {
                $propertyData = $property->propertyData;
                $address = trim(implode(', ', array_filter([
                    $propertyData?->address,
                    $propertyData?->city,
                ])));

                $notificationData['property'] =
                    $propertyData?->title
                    ?? (string) ($property->id + Properties::FRONTEND_PROPERTY_ID_INDEXING);
                $notificationData['address'] = $address;
                $notificationSubject = 'New renting request';
                $notificationTemplate = 'renting';
            }

            SendInternalNotificationJob::dispatch($notificationSubject, $notificationTemplate, $notificationData);
            ExportModelToSpreadsheetJob::dispatch(SearchRenting::class, 'Search Rentings');
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        try {
            SendSearchRentingMailerJob::dispatch($searchRenting->id);
        } catch (Exception $error) {
            Log::error('Error dispatching room searching applied email: ' . $error->getMessage(), [
                'search_renting_id' => $searchRenting->id ?? null,
            ]);
        }

        return ApiResponseClass::sendSuccess();
    }

    public function sendAppliedEmail(Request $request, SearchRentingMailerService $searchRentingMailer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:search_rentings,id',
            'email' => 'required_without:id|nullable|string|email',
            'locale' => 'nullable|string|max:10',
            'language' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        try {
            if ($request->filled('id')) {
                $searchRenting = SearchRenting::findOrFail((int) $request->get('id'));
                $searchRentingMailer->sendRoomSearchingApplied($searchRenting);
            } else {
                $searchRentingMailer->sendRoomSearchingAppliedEmail(
                    (string) $request->get('email'),
                    (string) ($request->get('language') ?: $request->get('locale') ?: 'en'),
                );
            }
        } catch (Exception $error) {
            Log::error('Room searching applied email endpoint failed', [
                'id' => $request->get('id'),
                'email' => $request->get('email'),
                'error' => $error->getMessage(),
            ]);

            return ApiResponseClass::sendError('Could not send room searching applied email.');
        }

        return ApiResponseClass::sendSuccess([], 'Room searching applied email sent.');
    }

    public function listByCity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'city' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $city = $request->get('city');

        $searchers = SearchRenting::query()
            ->whereRaw('LOWER(city) = ?', [strtolower($city)])
            ->orderByDesc('created_at')
            ->get();

        return ApiResponseClass::sendSuccess($searchers);
    }

    public function promote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_renting_id' => 'required|integer|exists:search_rentings,id',
            'property_id'       => 'required|integer|exists:properties,id',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $searchRenting = SearchRenting::findOrFail($request->get('search_renting_id'));
        $propertyId    = (int) $request->get('property_id');

        $alreadyExists = Renting::where('property_id', $propertyId)
            ->where('email', $searchRenting->email)
            ->exists();

        if ($alreadyExists) {
            return ApiResponseClass::sendError('This searcher is already an applicant for this property.', 422);
        }

        try {
            Renting::create([
                'property_id'  => $propertyId,
                'property'     => (string) ($propertyId + \App\Constants\Properties::FRONTEND_PROPERTY_ID_INDEXING),
                'name'         => $searchRenting->name,
                'surname'      => $searchRenting->surname,
                'phone'        => $searchRenting->phone,
                'email'        => $searchRenting->email,
                'note'         => $searchRenting->note,
                'referral_code' => $searchRenting->referral_code,
                'locale'       => $searchRenting->locale ?: 'en',
                'interface'    => $searchRenting->interface ?? 'web',
                'letter'       => $searchRenting->letter,
            ]);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}
