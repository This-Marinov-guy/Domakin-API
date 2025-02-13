<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Models\Viewing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;


class ViewingController extends Controller
{
    public function list(): JsonResponse
    {
        $viewings = Viewing::all()->toArray();
        //viewings = $query->get()->toArray();

        return ApiResponseClass::sendSuccess($viewings);
    }

    public function details($id)
    {
        $viewing = Viewing::find($id);

        if (!$viewing) {
            return ApiResponseClass::sendError('Viewing not found');
        }

        return ApiResponseClass::sendSuccess($viewing->toArray());
    }

    public function create(Request $request, GoogleSheetsService $sheetsService): JsonResponse
    {
        $validator = Validator::make($request->all(), Viewing::rules(), Viewing::messages());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        try {
            Viewing::create($request->all());
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        try {
            (new Notification('New viewing request', 'viewing', $request->all()))->sendNotification();

            $sheetsService->exportModelToSpreadsheet(
                Viewing::class,
                'Viewings'
            );
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}
