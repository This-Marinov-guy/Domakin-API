<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\GoogleServices\GoogleCalendarService;
use App\Services\Payment\PaymentLinkService;
use App\Models\Viewing;
use App\Services\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Carbon\Carbon;


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

    public function create(Request $request, GoogleSheetsService $sheetsService, GoogleCalendarService $calendarService, PaymentLinkService $paymentLinks): JsonResponse
    {
        $data = Helpers::camelToSnakeObject($request->all());

        $validator = Validator::make($data, Viewing::rules(), Viewing::messages());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), Viewing::messages());
        }

        try {
            $viewing = Viewing::create($data);
        } catch (Exception $error) {
            return ApiResponseClass::sendError($error->getMessage());
        }

        // Create Stripe payment link based on viewing time (NL timezone)
        try {
            $viewingDateTime = Carbon::createFromFormat('d-m-Y H:i', $data['date'] . ' ' . $data['time'], 'Europe/Amsterdam');
            $nowNl = Carbon::now('Europe/Amsterdam');
            $isStandard = $viewingDateTime->gt($nowNl->copy()->addDay());
            $paymentLinkName = $isStandard ? 'Viewing Fee' : 'Express Viewing Fee';
            $amountEur = $isStandard ? 50 : 100;

            $paymentLink = $paymentLinks->createPropertyFeeLink($amountEur, $paymentLinkName, [
                'checkout_type' => 'viewing',
                'viewing_id' => (string) $viewing->id,
            ]);
            if ($paymentLink) {
                $viewing->update(['payment_link' => $paymentLink]);
                // Append to the specified Google Sheet
                $sheetId = '1asA0dtjw7jk7BADin97SaMNDiB_Eb0m6yClPKp-6iAQ';
                $typeValue = $isStandard ? 'standard' : 'express';
                // Update the first row with empty ID instead of appending
                $sheetsService->updateFirstEmptyIdRow($sheetId, 'viewingsAndLinks', [
                    $viewing->id,
                    $data['name'] . ' ' . $data['surname'],
                    $data['date'],
                    $data['time'],
                    $data['address'] . ' ' . $data['city'],
                    $typeValue,
                    '', // Went (leave unchecked)
                    $paymentLink,
                    '', // Paid (leave unchecked)
                ]);
            } else {
                Log::warning('Failed to create Stripe payment link for viewing', [
                    'viewing_id' => $viewing->id,
                    'amount' => $amountEur,
                ]);
            }
        } catch (Exception $error) {
            Log::error('Error creating Stripe payment link: ' . $error->getMessage(), [
                'viewing_id' => $viewing->id ?? null
            ]);
        }

        // Create Google Calendar event
        try {
            // Transform date format from "23-05-2025" to "2025-05-23"
            $dateParts = explode('-', $data['date']);
            if (count($dateParts) === 3) {
                $formattedDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
            } else {
                $formattedDate = $data['date']; // fallback if format is unexpected
            }

            // Transform time format from "13:00" to "13:00" (already correct)
            $formattedTime = $data['time'];

            // Create event title
            $eventTitle = "Viewing for " . $data['name'] . ", " . $data['city'];

            // Create event description
            $description = "Name: " . $data['name'] . " " . $data['surname'] . "\n";
            $description .= "Phone: " . $data['phone'] . "\n";
            $description .= "Email: " . $data['email'] . "\n";
            
            if (!empty($data['note'])) {
                $description .= "Note: " . $data['note'];
            }

            // Create calendar event
            $eventId = $calendarService->createEvent(
                date: $formattedDate,
                time: $formattedTime,
                description: $description,
                title: $eventTitle,
                location: $data['address'] . ', ' . $data['city'],
                durationMinutes: 30
            );

            if ($eventId) {
                // Update the viewing record with the Google Calendar event ID
                $viewing->update(['google_calendar_id' => $eventId]);
                
                Log::info('Google Calendar event created for viewing', [
                    'viewing_id' => $viewing->id,
                    'event_id' => $eventId,
                    'date' => $formattedDate,
                    'time' => $formattedTime
                ]);
            } else {
                Log::warning('Failed to create Google Calendar event for viewing', [
                    'viewing_id' => $viewing->id
                ]);
            }
        } catch (Exception $error) {
            Log::error('Error creating Google Calendar event: ' . $error->getMessage(), [
                'viewing_id' => $viewing->id ?? null
            ]);
        }

        try {
            (new Notification('New viewing request', 'viewing', $data))->sendNotification();
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }
}
