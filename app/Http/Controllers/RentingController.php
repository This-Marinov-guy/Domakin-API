<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Models\Property;
use App\Models\Renting;
use Illuminate\Http\Request;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\RentingService;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @OA\Tag(name="Renting")
 */
class RentingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/renting/create",
     *     summary="Submit a renting application",
     *     tags={"Renting"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"property", "name", "surname", "phone", "email", "letter", "interface"},
     *                 @OA\Property(property="property", type="string", description="Property ID", example="123"),
     *                 @OA\Property(property="name", type="string", example="John"),
     *                 @OA\Property(property="surname", type="string", example="Doe"),
     *                 @OA\Property(property="phone", type="string", example="+31 6 12345678"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="letter", type="file", description="Motivational letter (PDF/DOC/DOCX)"),
     *                 @OA\Property(property="note", type="string", example="Additional notes about the application"),
     *                 @OA\Property(property="referralCode", type="string", example="REF123"),
     *                 @OA\Property(property="interface", type="string", enum={"web", "mobile", "signal"}, example="signal", description="Interface source"),
     *             ),
     *             @OA\Examples(
     *                 example="signal_interface",
     *                 summary="Signal Interface Example",
     *                 value={
     *                     "property": "123",
     *                     "name": "John",
     *                     "surname": "Doe",
     *                     "phone": "+31 6 12345678",
     *                     "email": "john@example.com",
     *                     "letter": "<file>",
     *                     "note": "Additional notes about the application",
     *                     "referralCode": "REF123",
     *                     "interface": "signal",
     *                 }
     *             ),
     *             @OA\Examples(
     *                 example="web_interface",
     *                 summary="Web Interface Example",
     *                 value={
     *                     "property": "123",
     *                     "name": "Jane",
     *                     "surname": "Smith",
     *                     "phone": "+31 6 98765432",
     *                     "email": "jane@example.com",
     *                     "letter": "<file>",
     *                     "note": "Web application notes",
     *                     "referralCode": "WEB123",
     *                     "interface": "web"
     *                 }
     *             ),
     *             @OA\Examples(
     *                 example="mobile_interface",
     *                 summary="Mobile Interface Example",
     *                 value={
     *                     "property": "123",
     *                     "name": "Bob",
     *                     "surname": "Johnson",
     *                     "phone": "+31 6 55555555",
     *                     "email": "bob@example.com",
     *                     "letter": "<file>",
     *                     "note": "Mobile application notes",
     *                     "referralCode": "MOB123",
     *                     "interface": "mobile"
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application submitted successfully",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="status", type="boolean", example=true)
     *             ),
     *             example={"status": true}
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
        $data = [
            'property' => $request->get('property'),
            'name' => $request->get('name'),
            'surname' => $request->get('surname'),
            'phone' => $request->get('phone'),
            'address' => $request->get('address'),
            'email' => $request->get('email'),
            'letter' => $request->file('letter'),
            'note' => $request->get('note'),
            'referral_code' => $request->get('referralCode'),
            'interface' => $request->get('interface'),
            'terms' => json_decode($request->get('terms'), true),
        ];

        $validator = Validator::make($data, Renting::rules($request));

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Renting::messages());
        }

        $data['letter'] = $cloudinary->singleUpload($data['letter'], [
            'public_id' => uniqid() . '.' .  $request->file('letter')->getClientOriginalExtension(),
            'resource_type' => 'raw',
            'folder' => "motivational_letters",
        ]);

        try {
            Renting::create($data);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
            (new Notification('New renting request', 'renting', $data))->sendNotification();

            $sheetsService->exportModelToSpreadsheet(
                Renting::class,
                'Rentings'
            );
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/renting/{id}",
     *     summary="Get all rentings for a property by property_id (admin)",
     *     tags={"Renting"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Property ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Property not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if (!Property::where('id', $id)->exists()) {
            return ApiResponseClass::sendError('Property not found', 404);
        }

        $rentings = Renting::query()
            ->where('property_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return ApiResponseClass::sendSuccess($rentings);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/renting/list",
     *     summary="List all rentings by property_id (admin)",
     *     tags={"Renting"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="property_id", in="query", required=true, description="Property ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function list(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'property_id' => 'required|integer|exists:properties,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);

        $paginator = Renting::query()
            ->where('property_id', $request->input('property_id'))
            ->with(['property.propertyData', 'internalUpdatedBy'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->items();

        return ApiResponseClass::sendSuccess([
            'rentings' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/renting/edit",
     *     summary="Update renting status and internal note (admin)",
     *     tags={"Renting"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", example="reviewed"),
     *             @OA\Property(property="internal_note", type="string", example="Internal note text")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated successfully"),
     *     @OA\Response(response=404, description="Renting not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function edit(Request $request, RentingService $rentingService): JsonResponse
    {
        $validator = RentingService::validateEdit($request->all());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $renting = $rentingService->updateRenting(
            $request->only(['id', 'status', 'internal_note']),
            $request->user()?->id
        );

        if (!$renting) {
            return ApiResponseClass::sendError('Renting not found', 404);
        }

        return ApiResponseClass::sendSuccess($renting);
    }
}
