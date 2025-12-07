<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Files\CloudinaryService;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Models\Career;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @OA\Tag(name="Career")
 */
class CareerController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/career/apply",
     *     summary="Submit a career application",
     *     tags={"Career"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "email", "phone", "position", "location"},
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+31 6 12345678"),
     *                 @OA\Property(property="position", type="string", example="viewing_agent"),
     *                 @OA\Property(property="location", type="string", example="Amsterdam, Netherlands"),
     *                 @OA\Property(property="experience", type="string", description="Optional"),
     *                 @OA\Property(property="message", type="string", description="Optional"),
     *                 @OA\Property(property="resume", type="string", format="binary", description="Optional resume file (PDF/DOC/DOCX)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function apply(Request $request, CloudinaryService $cloudinary): JsonResponse
    {
        $data = [
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'phone' => $request->get('phone'),
            'position' => $request->get('position'),
            'location' => $request->get('location'),
            'experience' => $request->get('experience'),
            'message' => $request->get('message'),
            'resume' => $request->file('resume'),
        ];

        $validator = Validator::make($data, Career::rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Career::messages());
        }

        // Upload resume to Cloudinary if provided
        if ($request->hasFile('resume')) {
            try {
                $data['resume'] = $cloudinary->singleUpload($data['resume'], [
                    'public_id' => uniqid() . '.' . $request->file('resume')->getClientOriginalExtension(),
                    'resource_type' => 'raw',
                    'folder' => 'careers/cvs',
                ]);
            } catch (Exception $error) {
                Log::error('Resume upload failed: ' . $error->getMessage());
                return ApiResponseClass::sendError('Failed to upload resume: ' . $error->getMessage());
            }
        } else {
            $data['resume'] = null;
        }

        // Create career application record
        try {
            Career::create($data);
        } catch (Exception $error) {
            Log::error('Career application creation failed: ' . $error->getMessage());
            return ApiResponseClass::sendError($error->getMessage());
        }

        // Send email notification
        try {
            (new Notification('New career application', 'career', $data))->sendNotification();
        } catch (Exception $error) {
            Log::error('Career notification email failed: ' . $error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}

