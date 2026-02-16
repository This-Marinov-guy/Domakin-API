<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Enums\MailTemplates;
use App\Models\EmailReminder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReminderController extends Controller
{
    /**
     * Add a row to email_reminders for a listing reminder.
     *
     * @OA\Post(
     *     path="/api/v1/reminder",
     *     summary="Schedule a listing email reminder",
     *     tags={"Reminders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","scheduled_date"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="scheduled_date", type="string", format="date", example="2026-03-01"),
     *             @OA\Property(property="metadata", type="object", description="Optional JSON object stored as-is")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reminder created"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function sendListingReminder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'date' => 'required|date',
            'metadata' => 'nullable', // array or JSON string; normalized below
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray());
        }

        $metadata = $request->input('metadata');
        if ($metadata !== null && ! is_array($metadata)) {
            if (is_string($metadata)) {
                $decoded = json_decode($metadata, true);
                $metadata = is_array($decoded) ? $decoded : null;
            } else {
                $metadata = null;
            }
        }

        $scheduledDate = Carbon::parse($request->input('date'))->subDays(2)->format('Y-m-d');

        $reminder = EmailReminder::create([
            'email' => $request->input('email'),
            'scheduled_date' => $scheduledDate,
            'template_id' => MailTemplates::LISTING_REMINDER->value,
            'metadata' => $metadata,
        ]);

        return ApiResponseClass::sendSuccess($reminder->fresh());
    }
}
