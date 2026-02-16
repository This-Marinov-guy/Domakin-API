<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Models\ListingApplication;
use App\Models\PersonalData;
use App\Models\Property;
use App\Models\PropertyData;
use App\Services\ListingApplicationService;
use App\Services\Payment\PaymentLinkService;
use App\Services\PropertyService;
use App\Services\UserService;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Listing Applications")
 */
class ListingApplicationController extends Controller
{
    // ---------------------------------------------------------------
    // POST – validate step 2: name, surname, email, phone, terms
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/v1/listing-application/validate/step-2",
     *     summary="Validate step 1 (personal details + terms)",
     *     tags={"Listing Applications"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","surname","email","phone"},
     *             @OA\Property(property="name",    type="string",  example="John"),
     *             @OA\Property(property="surname", type="string",  example="Doe"),
     *             @OA\Property(property="email",   type="string",  format="email", example="john@example.com"),
     *             @OA\Property(property="phone",   type="string",  example="+31612345678"),
     *             @OA\Property(property="terms",   type="object",  description="Required only for certain domains (contact/legals accepted)",
     *                 @OA\Property(property="contact", type="boolean", example=true),
     *                 @OA\Property(property="legals",  type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Validation passed"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function validateStep2(Request $request, ListingApplicationService $listingApplicationService): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            ListingApplication::step2Rules($request),
            ListingApplication::messages()
        );

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), ListingApplication::messages());
        }

        try {
            $application = $listingApplicationService->saveDraft($request, stepOverride: 3);
            if ($application === null) {
                return ApiResponseClass::sendError('Listing application not found');
            }
        } catch (\Exception $e) {
            return ApiResponseClass::sendError($e->getMessage());
        }

        return ApiResponseClass::sendSuccess(
            array_merge($application->toArray(), ['referenceId' => $application->reference_id])
        );
    }

    // ---------------------------------------------------------------
    // POST – validate step 3: type, address, postcode, registration
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/v1/listing-application/validate/step-3",
     *     summary="Validate step 3 (property details)",
     *     tags={"Listing Applications"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","address","postcode","registration","available_from"},
     *             @OA\Property(property="type",           type="integer", example=1),
     *             @OA\Property(property="address",        type="string",  example="Herengracht 123"),
     *             @OA\Property(property="postcode",       type="string",  example="1015 BZ"),
     *             @OA\Property(property="registration",   type="boolean", example=true),
     *             @OA\Property(property="available_from", type="string",  format="date", example="2025-03-01"),
     *             @OA\Property(property="available_to",   type="string",  format="date", example="2025-09-01")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Validation passed"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function validateStep3(Request $request, ListingApplicationService $listingApplicationService): JsonResponse
    {
        $validator = Validator::make($request->all(), ListingApplication::step3Rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), []);
        }

        try {
            $application = $listingApplicationService->saveDraft($request, stepOverride: 4);
            if ($application === null) {
                return ApiResponseClass::sendError('Listing application not found');
            }
        } catch (\Exception $e) {
            return ApiResponseClass::sendError($e->getMessage());
        }

        return ApiResponseClass::sendSuccess(
            array_merge($application->toArray(), ['referenceId' => $application->reference_id])
        );
    }

    // ---------------------------------------------------------------
    // POST – validate step 3: images
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/v1/listing-application/validate/step-4",
     *     summary="Validate step 4 (images)",
     *     tags={"Listing Applications"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"images"},
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Validation passed"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function validateStep4(Request $request, ListingApplicationService $listingApplicationService): JsonResponse
    {
        $validator = Validator::make($request->all(), ListingApplication::step4Rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), []);
        }

        try {
            $application = $listingApplicationService->saveDraft($request, stepOverride: 5);
            if ($application === null) {
                return ApiResponseClass::sendError('Listing application not found');
            }
        } catch (\Exception $e) {
            return ApiResponseClass::sendError($e->getMessage());
        }

        return ApiResponseClass::sendSuccess(
            array_merge($application->toArray(), ['referenceId' => $application->reference_id])
        );
    }

    // ---------------------------------------------------------------
    // POST – validate step 5: all remaining fields
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/v1/listing-application/validate/step-5",
     *     summary="Validate step 5 (remaining property details)",
     *     tags={"Listing Applications"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"city","size","rent","bills","description","furnished_type","bathrooms","toilets"},
     *             @OA\Property(property="city",           type="string",  example="Amsterdam"),
     *             @OA\Property(property="size",           type="string",  example="25m²"),
     *             @OA\Property(property="rent",           type="number",  example=850, minimum=1),
     *             @OA\Property(property="bills",          type="object"),
     *             @OA\Property(property="flatmates",      type="object"),
     *             @OA\Property(property="period",         type="object"),
     *             @OA\Property(property="description",    type="object"),
     *             @OA\Property(property="pets_allowed",   type="boolean", example=false),
     *             @OA\Property(property="smoking_allowed",type="boolean", example=false),
     *             @OA\Property(property="furnished_type", type="integer", example=1),
     *             @OA\Property(property="shared_space",   type="string"),
     *             @OA\Property(property="bathrooms",      type="integer", example=1),
     *             @OA\Property(property="toilets",        type="integer", example=1),
     *             @OA\Property(property="amenities",      type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Validation passed"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function validateStep5(Request $request, ListingApplicationService $listingApplicationService): JsonResponse
    {
        $validator = Validator::make($request->all(), ListingApplication::step5Rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), []);
        }

        try {
            $application = $listingApplicationService->saveDraft($request, stepOverride: 6);
            if ($application === null) {
                return ApiResponseClass::sendError('Listing application not found');
            }
        } catch (\Exception $e) {
            return ApiResponseClass::sendError($e->getMessage());
        }

        return ApiResponseClass::sendSuccess(
            array_merge($application->toArray(), ['referenceId' => $application->reference_id])
        );
    }

    // ---------------------------------------------------------------
    // POST – save draft (create or update)
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/v1/listing-application/save",
     *     summary="Save listing application draft",
     *     tags={"Listing Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="referenceId", type="string", format="uuid", description="Existing application reference ID to update (omit to create new)"),
     *             @OA\Property(property="step", type="integer", example=1, description="Current step number (1–5)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Draft saved", @OA\JsonContent(@OA\Property(property="status", type="boolean", example=true), @OA\Property(property="data", type="object"))),
     *     @OA\Response(response=400, description="Error")
     * )
     */
    public function save(Request $request, ListingApplicationService $listingApplicationService): JsonResponse
    {
        try {
            $application = $listingApplicationService->saveDraft($request);
            if ($application === null) {
                return ApiResponseClass::sendError('Listing application not found');
            }
        } catch (\Exception $e) {
            return ApiResponseClass::sendError($e->getMessage());
        }

        return ApiResponseClass::sendSuccess(
            array_merge($application->toArray(), ['referenceId' => $application->reference_id])
        );
    }

    // ---------------------------------------------------------------
    // POST – submit (full validation + save as complete)
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/v1/listing-application/submit",
     *     summary="Submit listing application: create property from draft and delete application",
     *     tags={"Listing Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"referenceId"},
     *             @OA\Property(property="referenceId", type="string", format="uuid", description="Reference ID of the listing application to submit")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Property created, application deleted"),
     *     @OA\Response(response=422, description="Validation failed"),
     *     @OA\Response(response=400, description="Error")
     * )
     */
    public function submit(Request $request, UserService $user, PropertyService $propertyService, PaymentLinkService $paymentLinks): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'referenceId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), []);
        }

        $userId = $user->extractIdFromRequest($request);
        if ($userId === null) {
            return ApiResponseClass::sendError('Unauthorized', null, 401);
        }

        $referenceId = $request->get('referenceId') ?? $request->get('reference_id');
        $application = ListingApplication::where('reference_id', $referenceId)
            ->where('user_id', $userId)
            ->first();

        if (!$application) {
            return ApiResponseClass::sendError('Listing application not found');
        }

        try {
            $property = DB::transaction(function () use ($application, $userId, $propertyService, $paymentLinks) {
                $personalData = [
                    'name'    => $application->name ?? '',
                    'surname' => $application->surname ?? '',
                    'email'   => $application->email ?? '',
                    'phone'   => $application->phone ?? '',
                ];

                $folder = substr(is_array($application->description)
                    ? ($application->description['en'] ?? (string) json_encode($application->description))
                    : (string) $application->description, 0, 10) . '|' . date('Y-m-d H:i:s');

                $imagesString = $application->images;
                if (is_array($imagesString)) {
                    $imagesString = implode(', ', $imagesString);
                }
                $imagesString = $imagesString !== null ? (string) $imagesString : '';

                $propertyData = [
                    'city'           => $application->city ?? '',
                    'address'        => $application->address ?? '',
                    'postcode'       => $application->postcode ?? '',
                    'size'           => $application->size ?? '',
                    'rent'           => $application->rent ?? '',
                    'registration'   => $application->registration ?? false,
                    'bills'          => $application->bills ?? [],
                    'flatmates'      => $application->flatmates ?? [],
                    'period'         => ($application->available_from ?? '') . ' - ' . ($application->available_to ?? ''),
                    'description'    => $application->description ?? [],
                    'images'         => $imagesString,
                    'folder'         => $folder,
                    'pets_allowed'   => $application->pets_allowed ?? false,
                    'smoking_allowed' => $application->smoking_allowed ?? false,
                    'type'           => $application->type,
                    'furnished_type' => $application->furnished_type,
                    'shared_space'   => $application->shared_space,
                    'bathrooms'      => $application->bathrooms,
                    'toilets'        => $application->toilets,
                    'amenities'      => $application->amenities !== null ? (string) $application->amenities : null,
                    'available_from' => $application->available_from,
                    'available_to'   => $application->available_to,
                ];

                if (empty($propertyData['title'])) {
                    $propertyData['title'] = 'Available room';
                }
                $propertyData = $propertyService->modifyPropertyDataWithTranslations($propertyData);

                $property = Property::create([
                    'created_by'      => $userId,
                    'last_updated_by' => $userId,
                    'interface'       => 'web',
                ]);

                PersonalData::create([
                    'property_id' => $property->id,
                    ...$personalData,
                ]);

                $rent = (float) $application->rent;
                $paymentLink = null;
                if ($rent > 0 && $imagesString !== '') {
                    $mainImage = trim(explode(',', $imagesString)[0]);
                    if ($mainImage !== '') {
                        $paymentLink = $paymentLinks->createPropertyFeeLink($rent, imageSrc: $mainImage);
                    }
                }

                PropertyData::create([
                    'property_id'   => $property->id,
                    ...$propertyData,
                    'payment_link'  => $paymentLink,
                ]);

                $application->delete();

                return $property;
            });
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, '25P02') || str_contains($message, 'aborted')) {
                $message = 'A database error occurred during submit. Please check that all required fields are filled. Original: ' . $message;
            }
            return ApiResponseClass::sendError($message);
        }

        return ApiResponseClass::sendSuccess($property);
    }

    // ---------------------------------------------------------------
    // GET – list all applications for the authenticated user
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/listing-application/list",
     *     summary="Get all listing applications for the current user",
     *     tags={"Listing Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (1-100)", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="city", in="query", description="Filter by city (partial match)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", description="Search in email, name or surname", @OA\Schema(type="string")),
     *     @OA\Parameter(name="referenceId", in="query", description="Filter by reference ID (UUID, exact match)", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(@OA\Property(property="status", type="boolean", example=true), @OA\Property(property="data", type="object", @OA\Property(property="data", type="array", @OA\Items(type="object")), @OA\Property(property="current_page", type="integer"), @OA\Property(property="last_page", type="integer"), @OA\Property(property="per_page", type="integer"), @OA\Property(property="total", type="integer")))
     *     )
     * )
     */
    public function list(Request $request, UserService $user): JsonResponse
    {
        $userId = $user->extractIdFromRequest($request);

        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min(100, $perPage));
        $page = max(1, (int) $request->get('page', 1));

        $query = ListingApplication::where('user_id', $userId);
        $query = $this->applyListingApplicationFilters($query, $request);

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponseClass::sendSuccess([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    // ---------------------------------------------------------------
    // GET – list all applications (admin)
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/listing-application/list-all",
     *     summary="Get all listing applications (admin)",
     *     tags={"Listing Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (1-100)", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="city", in="query", description="Filter by city (partial match)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", description="Search in email, name or surname", @OA\Schema(type="string")),
     *     @OA\Parameter(name="referenceId", in="query", description="Filter by reference ID (UUID, exact match)", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(@OA\Property(property="status", type="boolean", example=true), @OA\Property(property="data", type="object", @OA\Property(property="data", type="array", @OA\Items(type="object")), @OA\Property(property="current_page", type="integer"), @OA\Property(property="last_page", type="integer"), @OA\Property(property="per_page", type="integer"), @OA\Property(property="total", type="integer")))
     *     )
     * )
     */
    public function listAll(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min(100, $perPage));
        $page = max(1, (int) $request->get('page', 1));

        $query = ListingApplication::query();
        $query = $this->applyListingApplicationFilters($query, $request);

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponseClass::sendSuccess([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Apply city, search (email/name/surname) and referenceId filters to listing application query.
     *
     * @param \Illuminate\Database\Eloquent\Builder<ListingApplication> $query
     * @return \Illuminate\Database\Eloquent\Builder<ListingApplication>
     */
    private function applyListingApplicationFilters(\Illuminate\Database\Eloquent\Builder $query, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $referenceId = $request->get('referenceId') ?? $request->get('reference_id');
        $city = $request->get('city');
        $search = $request->get('search');

        $query->when(
            !empty($referenceId),
            fn($q) =>
            $q->whereRaw('CAST(reference_id AS TEXT) ILIKE ?', ["%" . trim($referenceId) . "%"])
        );

        $query->when(
            !empty($city),
            fn($q) =>
            $q->where('city', 'ILIKE', '%' . trim($city) . '%')
        );

        if ($search) {
            $term = '%' . strtolower(trim((string) $search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(surname, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(address, \'\')) LIKE ?', [$term]);
            });
        }

        return $query;
    }

    // ---------------------------------------------------------------
    // GET – retrieve single application by id
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/listing-application/{referenceId}",
     *     summary="Get a listing application by reference ID",
     *     tags={"Listing Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="referenceId", in="path", required=true, description="Application reference ID (UUID)", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=400, description="Not found")
     * )
     */
    public function show(Request $request, string $referenceId, UserService $user): JsonResponse
    {
        $userId = $user->extractIdFromRequest($request);

        $application = ListingApplication::where('reference_id', $referenceId)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->first();

        if (!$application) {
            return ApiResponseClass::sendError('Listing application not found');
        }

        return ApiResponseClass::sendSuccess($application);
    }

    // ---------------------------------------------------------------
    // PATCH – update application by id
    // ---------------------------------------------------------------

    /**
     * @OA\Patch(
     *     path="/api/v1/listing-application/edit",
     *     summary="Update a listing application",
     *     tags={"Listing Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", description="Application ID to update")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated successfully"),
     *     @OA\Response(response=400, description="Not found or error")
     * )
     */
    public function edit(Request $request, UserService $user, ListingApplicationService $listingApplicationService): JsonResponse
    {
        $userId = $user->extractIdFromRequest($request);
        if ($userId === null) {
            return ApiResponseClass::sendError('Unauthorized', null, 401);
        }

        $application = ListingApplication::where('id', $request->get('id'))
            ->where('user_id', $userId)
            ->first();

        if (!$application) {
            return ApiResponseClass::sendError('Listing application not found');
        }

        $data = $request->except(['id', 'user_id', 'new_images']);
        // Reuse service-level key normalization so edit/save behave identically
        $data = $listingApplicationService->mapCamelToSnakeKeys($data);

        if ($userId !== null) {
            $data['user_id'] = $userId;
        }

        // images (string) = reordered existing; new_images (array) = upload and append to the back
        if ($request->has('images') || $request->hasFile('new_images')) {
            $data['images'] = $listingApplicationService->resolveImagesString($request, $application->reference_id);
        }

        try {
            $application->update($data);
        } catch (\Exception $e) {
            return ApiResponseClass::sendError($e->getMessage());
        }

        return ApiResponseClass::sendSuccess(
            array_merge($application->toArray(), ['referenceId' => $application->reference_id])
        );
    }

    // ---------------------------------------------------------------
    // DELETE – delete application by id
    // ---------------------------------------------------------------

    /**
     * @OA\Delete(
     *     path="/api/v1/listing-application/delete",
     *     summary="Delete a listing application",
     *     tags={"Listing Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted successfully"),
     *     @OA\Response(response=400, description="Not found")
     * )
     */
    public function destroy(Request $request, UserService $user): JsonResponse
    {
        $userId = $user->extractIdFromRequest($request);

        $application = ListingApplication::where('id', $request->get('id'))
            ->where('user_id', $userId)
            ->first();

        if (!$application) {
            return ApiResponseClass::sendError('Listing application not found');
        }

        $application->delete();

        return ApiResponseClass::sendSuccess(['message' => 'Listing application deleted successfully']);
    }
}
