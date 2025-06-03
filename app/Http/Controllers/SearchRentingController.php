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
    public function create(Request $request, CloudinaryService$cloudinary, GoogleSheetsService $sheetsService): JsonResponse
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
