<?php

namespace App\Services\GoogleServices;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class GoogleCalendarService
{
    protected $client;
    protected $service;
    protected $calendarId;

    public function __construct()
    {
        $this->calendarId = env('GOOGLE_CALENDAR_ID', 'primary');
        $this->initializeGoogleClient();
    }

    protected function initializeGoogleClient()
    {
        try {
            $credentialsPath = base_path('google-credentials.json');

            if (!file_exists($credentialsPath)) {
                throw new Exception('Google credentials file not found at: ' . $credentialsPath);
            }

            $this->client = new Client();
            $this->client->setAuthConfig($credentialsPath);
            $this->client->addScope('https://www.googleapis.com/auth/calendar');

            $this->service = new Calendar($this->client);
        } catch (Exception $e) {
            Log::error('Failed to initialize Google Calendar client: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a calendar event
     *
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format (24-hour)
     * @param string $description Event description
     * @param string $title Event title (optional)
     * @param string $location Event location (optional)
     * @param int $durationMinutes Event duration in minutes (default: 60)
     * @return string|null Event ID if successful, null if failed
     */
    public function createEvent(
        string $date,
        string $time,
        string $description,
        string $title = '',
        string $location = '',
        int $durationMinutes = 60
    ): ?string {
        // Only create calendar events in production
        if (env('APP_ENV') !== 'prod') {
            Log::info('Calendar event creation skipped - not in production environment', [
                'title' => $title,
                'date' => $date,
                'time' => $time,
                'environment' => env('APP_ENV')
            ]);
            return null;
        }

        try {
            // Parse date and time
            $dateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time, 'Europe/Amsterdam');
            
            if (!$dateTime) {
                throw new Exception('Invalid date or time format');
            }

            // Create event start time
            $startDateTime = new EventDateTime();
            $startDateTime->setDateTime($dateTime->toISOString());
            $startDateTime->setTimeZone('Europe/Amsterdam');

            // Create event end time
            $endDateTime = new EventDateTime();
            $endDateTime->setDateTime($dateTime->addMinutes($durationMinutes)->toISOString());
            $endDateTime->setTimeZone('Europe/Amsterdam');

            // Create the event
            $event = new Event();
            $event->setSummary($title ?: 'New Event');
            $event->setDescription($description);
            $event->setStart($startDateTime);
            $event->setEnd($endDateTime);
            
            if ($location) {
                $event->setLocation($location);
            }

            // Insert the event
            $createdEvent = $this->service->events->insert($this->calendarId, $event);

            Log::info('Calendar event created successfully', [
                'event_id' => $createdEvent->getId(),
                'title' => $title,
                'date' => $date,
                'time' => $time
            ]);

            return $createdEvent->getId();

        } catch (Exception $e) {
            Log::error('Failed to create calendar event: ' . $e->getMessage(), [
                'date' => $date,
                'time' => $time,
                'description' => $description
            ]);
            return null;
        }
    }

    /**
     * Create a calendar event with more flexible datetime input
     *
     * @param string $dateTime DateTime string (will be parsed)
     * @param string $description Event description
     * @param string $title Event title (optional)
     * @param string $location Event location (optional)
     * @param int $durationMinutes Event duration in minutes (default: 60)
     * @return string|null Event ID if successful, null if failed
     */
    public function createEventFromDateTime(
        string $dateTime,
        string $description,
        string $title = '',
        string $location = '',
        int $durationMinutes = 60
    ): ?string {
        // Only create calendar events in production
        if (env('APP_ENV') !== 'prod') {
            Log::info('Calendar event creation skipped - not in production environment', [
                'title' => $title,
                'datetime' => $dateTime,
                'environment' => env('APP_ENV')
            ]);
            return null;
        }

        try {
            // Try to parse the datetime string
            $parsedDateTime = Carbon::parse($dateTime, 'Europe/Amsterdam');
            
            if (!$parsedDateTime) {
                throw new Exception('Invalid datetime format');
            }

            return $this->createEvent(
                $parsedDateTime->format('Y-m-d'),
                $parsedDateTime->format('H:i'),
                $description,
                $title,
                $location,
                $durationMinutes
            );

        } catch (Exception $e) {
            Log::error('Failed to create calendar event from datetime: ' . $e->getMessage(), [
                'datetime' => $dateTime,
                'description' => $description
            ]);
            return null;
        }
    }

    /**
     * Delete a calendar event by ID
     *
     * @param string $eventId
     * @return bool
     */
    public function deleteEvent(string $eventId): bool
    {
        try {
            $this->service->events->delete($this->calendarId, $eventId);
            
            Log::info('Calendar event deleted successfully', [
                'event_id' => $eventId
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete calendar event: ' . $e->getMessage(), [
                'event_id' => $eventId
            ]);
            return false;
        }
    }

    /**
     * Get event details by ID
     *
     * @param string $eventId
     * @return array|null
     */
    public function getEvent(string $eventId): ?array
    {
        try {
            $event = $this->service->events->get($this->calendarId, $eventId);
            
            return [
                'id' => $event->getId(),
                'title' => $event->getSummary(),
                'description' => $event->getDescription(),
                'location' => $event->getLocation(),
                'start' => $event->getStart()->getDateTime(),
                'end' => $event->getEnd()->getDateTime(),
                'timezone' => $event->getStart()->getTimeZone()
            ];
        } catch (Exception $e) {
            Log::error('Failed to get calendar event: ' . $e->getMessage(), [
                'event_id' => $eventId
            ]);
            return null;
        }
    }
} 