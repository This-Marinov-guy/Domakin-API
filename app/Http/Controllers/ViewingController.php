<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Jobs\SendInternalNotificationJob;
use App\Services\GoogleServices\GoogleSheetsService;
use App\Services\GoogleServices\GoogleCalendarService;
use App\Constants\Sheets;
use App\Constants\Payments;
use App\Models\Viewing;
use App\Services\Helpers;
use App\Services\UserService;
use App\Services\ViewingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Carbon\Carbon;


/**
 * @OA\Tag(name="Viewing")
 */
class ViewingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/viewing/list",
     *     summary="List all viewings (admin)",
     *     tags={"Viewing"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'city' => 'nullable|string',
            'search' => 'nullable|string',
            'reference_id' => 'nullable|string',
            'status' => 'nullable',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);

        $query = Viewing::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('city')) {
            $city = (string) $request->string('city')->trim();
            $query->where('city', 'ILIKE', '%' . $city . '%');
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search')->trim();
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('name', 'ILIKE', '%' . $search . '%')
                    ->orWhere('surname', 'ILIKE', '%' . $search . '%')
                    ->orWhere('email', 'ILIKE', '%' . $search . '%');
            });
        }

        if ($request->filled('reference_id')) {
            $referenceId = trim((string) $request->get('reference_id'));
            if (ctype_digit($referenceId)) {
                $query->whereKey((int) $referenceId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('status')) {
            $status = trim((string) $request->get('status'));
            if (ctype_digit($status)) {
                $query->whereRaw('COALESCE(status, 1) = ?', [(int) $status]);
            }
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResponseClass::sendSuccess([
            'viewings' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/viewing/details/{id}",
     *     summary="Get viewing details",
     *     tags={"Viewing"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Viewing ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Viewing not found"),
     *             @OA\Property(property="tag", type="string", example="account:authentication.errors.general")
     *         )
     *     )
     * )
     */
    public function details($id)
    {
        $viewing = Viewing::find($id);

        if (!$viewing) {
            return ApiResponseClass::sendError('Viewing not found');
        }

        return ApiResponseClass::sendSuccess($viewing);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/viewing/create",
     *     summary="Create a viewing appointment",
     *     tags={"Viewing"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"property_id", "name", "email", "phone", "date", "time", "note", "interface"},
     *             @OA\Property(property="property_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="+31 6 12345678"),
     *             @OA\Property(property="date", type="string", format="date", example="2025-12-10"),
     *             @OA\Property(property="time", type="string", example="14:00"),
     *             @OA\Property(property="note", type="string", example="Questions for the agent to ask during the viewing"),
     *             @OA\Property(property="interface", type="string", enum={"web", "mobile", "signal"}, example="web", description="Interface source")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Viewing created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true)
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
    public function create(Request $request, GoogleSheetsService $sheetsService, GoogleCalendarService $calendarService): JsonResponse
    {
        $data = Helpers::camelToSnakeObject($request->all());

        $validator = Validator::make($data, Viewing::rules($request), Viewing::messages());

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
            // Attach the source and viewing id so Stripe sends both back in the checkout webhook.
            $paymentLink = $baseLink
                . (str_contains($baseLink, '?') ? '&' : '?')
                . http_build_query([
                    'client_reference_id' => Payments::VIEWING_CLIENT_REFERENCE_PREFIX . $viewing->id,
                ]);
            if ($paymentLink) {
                $viewing->update(['payment_link' => $paymentLink]);
                // Append to the specified Google Sheet (skip on demo)
                if (config('sheets.export_enabled', true)) {
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
                        $paymentLink,
                        '', // Paid (leave unchecked)
                    ]);
                }
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
            
            $description .= "Questions: " . $data['note'];

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
            SendInternalNotificationJob::dispatch('New viewing request', 'viewing', $data);
        } catch (Exception $error) {
            Log::error($error->getMessage());
        }

        return ApiResponseClass::sendSuccess();
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/viewing/edit",
     *     summary="Update viewing status and internal note (admin)",
     *     tags={"Viewing"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="status", type="integer", example=2),
     *             @OA\Property(property="internal_note", type="string", example="Confirmed by phone")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated successfully"),
     *     @OA\Response(response=404, description="Viewing not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function edit(Request $request, ViewingService $viewingService, UserService $userService): JsonResponse
    {
        $validator = ViewingService::validateEdit($request->all());

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $internalUpdatedBy = $userService->extractIdFromRequest($request);
        $viewing = $viewingService->updateViewing(
            $request->only(['id', 'status', 'internal_note']),
            $internalUpdatedBy
        );

        if (!$viewing) {
            return ApiResponseClass::sendError('Viewing not found', 404);
        }

        return ApiResponseClass::sendSuccess($viewing);
    }
}
