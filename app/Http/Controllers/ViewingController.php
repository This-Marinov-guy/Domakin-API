<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Mail\Notification;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\GoogleServices\GoogleCalendarService;
use App\Constants\Sheets;
use App\Constants\Payments;
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

    public function create(Request $request, GoogleSheetsService $sheetsService, GoogleCalendarService $calendarService): JsonResponse
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

        // Select Stripe checkout link based on viewing time (NL timezone)
        try {
            $viewingDateTime = Carbon::createFromFormat('d-m-Y H:i', $data['date'] . ' ' . $data['time'], 'Europe/Amsterdam');
            $nowNl = Carbon::now('Europe/Amsterdam');
            $isStandard = $viewingDateTime->gt($nowNl->copy()->addDay());
            $baseLink = $isStandard ? Payments::STRIPE_VIEWING_STANDARD_LINK : Payments::STRIPE_VIEWING_EXPRESS_LINK;
            // Attach viewing id so webhook can identify the row via client_reference_id
            $paymentLink = $baseLink . (str_contains($baseLink, '?') ? '&' : '?') . 'client_reference_id=' . $viewing->id;
            if ($paymentLink) {
                $viewing->update(['payment_link' => $paymentLink]);
                // Append to the specified Google Sheet
                $sheetId = Sheets::VIEWINGS_SHEET_ID;
                $typeValue = $isStandard ? 'standard' : 'express';
                // Update the first row with empty ID instead of appending
                $sheetsService->updateFirstEmptyIdRow($sheetId, Sheets::VIEWINGS_TAB, [
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
                ]);
            }
        } catch (Exception $error) {
            Log::error('Error preparing Stripe payment link: ' . $error->getMessage(), [
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
