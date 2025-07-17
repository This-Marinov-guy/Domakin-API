<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Services\GoogleServices\GoogleCalendarService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class Test extends Controller
{
    /**
     * Test Google Calendar event creation
     */
    public function testCalendarEvent(Request $request, GoogleCalendarService $calendarService): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'description' => 'required|string',
            'title' => 'nullable|string',
            'location' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:15|max:480'
        ]);

        $eventId = $calendarService->createEvent(
            date: $request->date,
            time: $request->time,
            description: $request->description,
            title: $request->title ?? 'Test Event',
            location: $request->location ?? '',
            durationMinutes: $request->duration_minutes ?? 60
        );

        if ($eventId) {
            return ApiResponseClass::sendSuccess([
                'event_id' => $eventId,
                'message' => 'Calendar event created successfully'
            ]);
        }

        return ApiResponseClass::sendError('Failed to create calendar event');
    }

    /**
     * Test Google Calendar event creation with datetime string
     */
    public function testCalendarEventFromDateTime(Request $request, GoogleCalendarService $calendarService): JsonResponse
    {
        $request->validate([
            'datetime' => 'required|string',
            'description' => 'required|string',
            'title' => 'nullable|string',
            'location' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:15|max:480'
        ]);

        $eventId = $calendarService->createEventFromDateTime(
            dateTime: $request->datetime,
            description: $request->description,
            title: $request->title ?? 'Test Event',
            location: $request->location ?? '',
            durationMinutes: $request->duration_minutes ?? 60
        );

        if ($eventId) {
            return ApiResponseClass::sendSuccess([
                'event_id' => $eventId,
                'message' => 'Calendar event created successfully'
            ]);
        }

        return ApiResponseClass::sendError('Failed to create calendar event');
    }
} 