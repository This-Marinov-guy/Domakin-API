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

class RentingController extends Controller
{
    public function create(Request $request, CloudinaryService $cloudinary, GoogleSheetsService $sheetsService): JsonResponse
    {
        $data = [
            'property' => $request->get('property'),
            'name' => $request->get('name'),
            'surname' => $request->get('surname'),
            'phone' => $request->get('phone'),
            'email' => $request->get('email'),
            'letter' => $request->file('letter'),
            'note' => $request->get('note'),
            'referral_code' => $request->get('referralCode'),
            'terms' => json_decode($request->get('terms'), true),
        ];

        $validator = Validator::make($data, Renting::rules());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Renting::messages());
        }

        $data['letter'] = $cloudinary->singleUpload($data['letter'], [
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
