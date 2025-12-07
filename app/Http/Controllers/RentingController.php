<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Models\Renting;
use Illuminate\Http\Request;
use App\Services\GoogleServices\GoogleSheetsService;
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
     *                 required={"property", "name", "surname", "phone", "email", "letter", "interface", "terms"},
     *                 @OA\Property(property="property", type="string", description="Property ID", example="123"),
     *                 @OA\Property(property="name", type="string", example="John"),
     *                 @OA\Property(property="surname", type="string", example="Doe"),
     *                 @OA\Property(property="phone", type="string", example="+31 6 12345678"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="letter", type="file", description="Motivational letter (PDF/DOC/DOCX)"),
     *                 @OA\Property(property="note", type="string", example="Additional notes about the application"),
     *                 @OA\Property(property="referralCode", type="string", example="REF123"),
     *                 @OA\Property(property="interface", type="string", enum={"web", "mobile", "signal"}, example="signal", description="Interface source"),
     *                 @OA\Property(property="terms", type="string", description="JSON string with terms.contact and terms.legals")
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
     *                     "terms": "{contact:true,legals:true}"
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
     *                     "interface": "web",
     *                     "terms": "{contact:true,legals:true}"
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
     *                     "interface": "mobile",
     *                     "terms": "{contact:true,legals:true}"
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
}
