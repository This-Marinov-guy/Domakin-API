<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Models\SearchRenting;
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
     *     path="/api/renting/searching/create",
     *     summary="Submit a property search request",
     *     tags={"Renting"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "surname", "phone", "email", "people", "type", "moveIn", "period", "registration", "budget", "city", "terms"},
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
     *                 @OA\Property(property="note", type="string"),
     *                 @OA\Property(property="referralCode", type="string"),
     *                 @OA\Property(property="letter", type="string", format="binary", description="Optional motivational letter"),
     *                 @OA\Property(property="terms", type="string", description="JSON string with terms")
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
     *             @OA\Property(property="invalid_fields", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="tag", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message"),
     *             @OA\Property(property="tag", type="string")
     *         )
     *     )
     * )
     */
    public function create(Request $request, CloudinaryService $cloudinary, GoogleSheetsService $sheetsService): JsonResponse
    {
        $data = [
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
            'letter' => $request->file('letter') ?? null,
            'terms' => json_decode($request->get('terms'), true),
        ];

        $validator = Validator::make($data, SearchRenting::rules());

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
            SearchRenting::create($data);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
            (new Notification('New searching for Renting', 'search_renting', $data))->sendNotification();

            $sheetsService->exportModelToSpreadsheet(
                SearchRenting::class,
                'Search Rentings'
            );
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}
