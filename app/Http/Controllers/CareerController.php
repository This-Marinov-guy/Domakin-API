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

class CareerController extends Controller
{
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

