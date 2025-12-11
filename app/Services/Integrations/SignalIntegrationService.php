<?php

namespace App\Services\Integrations;

use App\Constants\Emails;
use App\Models\Property;
use App\Services\Helpers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SignalIntegrationService
{
    private const API_ENDPOINT = 'https://api.signaal.app/webhooks/domakin';
    private const EVENT_TYPE = 'property.created';

    /**
     * Submit property to Signal API
     *
     * @param Property $property
     * @return array|null Returns response data on success, null on failure
     * @throws Exception
     */
    public function submitProperty(Property $property): ?array
    {
        if (env('APP_ENV') !== 'prod') {
            return null;
        }

        try {
            $token = env('SIGNAL_AUTH_TOKEN');

            if (empty($token)) {
                Log::error('Signal API: SIGNAL_AUTH_TOKEN is not configured');
                throw new Exception('Signal API token is not configured');
            }

            $property->load(['propertyData']);

            $payload = $this->transformPropertyToSignalFormat($property);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post(self::API_ENDPOINT, $payload);

            if (!$response->successful()) {
                Log::error('Signal API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'property_id' => $property->id,
                    'payload' => $payload,
                    'headers' => $response->headers(),
                ]);
                throw new Exception('Signal API request failed: ' . $response->body());
            }

            Log::info('Property submitted to Signal API successfully', [
                'property_id' => $property->id,
            ]);

            return $response->json();
        } catch (Exception $e) {
            Log::error('Error submitting property to Signal API: ' . $e->getMessage(), [
                'property_id' => $property->id ?? null,
                'payload' => $payload ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * Delete property from Signal API
     *
     * @param Property $property
     * @return array|null Returns response data on success, null on failure
     * @throws Exception
     */
    public function deleteProperty(Property $property): ?array
    {
        if (env('APP_ENV') !== 'prod') {
            return null;
        }

        try {
            $token = env('SIGNAL_AUTH_TOKEN');

            if (empty($token)) {
                Log::error('Signal API: SIGNAL_AUTH_TOKEN is not configured');
                throw new Exception('Signal API token is not configured');
            }

            $payload = [
                'type' => 'property.deleted',
                'data' => [(string)$property->id],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post(self::API_ENDPOINT, $payload);

            if (!$response->successful()) {
                Log::error('Signal API delete request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'property_id' => $property->id,
                    'payload' => $payload,
                    'headers' => $response->headers(),
                ]);
                throw new Exception('Signal API delete request failed: ' . $response->body());
            }

            Log::info('Property deleted from Signal API successfully', [
                'property_id' => $property->id,
            ]);

            return $response->json();
        } catch (Exception $e) {
            Log::error('Error deleting property from Signal API: ' . $e->getMessage(), [
                'property_id' => $property->id ?? null,
                'payload' => $payload ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * Transform Property model to Signal API format
     *
     * @param Property $property
     * @return array
     */
    private function transformPropertyToSignalFormat(Property $property): array
    {
        $propertyData = $property->propertyData;

        // Parse images from comma-separated string to array
        $images = [];
        if (!empty($propertyData->images)) {
            $images = array_map('trim', explode(',', $propertyData->images));
        }

        // Decode stored JSON strings (if any) and get translated values
        $rawTitle = $this->decodeJsonIfNeeded($propertyData->title ?? null);
        $rawDescription = $this->decodeJsonIfNeeded($propertyData->description ?? null);

        $title = Helpers::getTranslatedValue($rawTitle, 'en', false, 'Available room');
        $description = Helpers::getTranslatedValue($rawDescription, 'en', false, '');

        // dd($title, $description);

        // Format city (lowercase)
        $city = strtolower($propertyData->city ?? '');

        // Format location (postcode + city area if available); ensure non-empty to satisfy schema
        $location = $this->formatLocation($propertyData->postcode ?? '', $propertyData->city ?? '');
        if (empty($location)) {
            $location = $city ?: 'unknown';
        }

        // Build property URL (you may need to adjust this based on your frontend URL structure)
        $frontendUrl = env('FRONTEND_URL', 'https://domakin.nl');
        $url = rtrim($frontendUrl, '/') . '/services/renting/property/' . $property->id;

        // Coordinates (schema requires longitude/latitude; provide defaults if unknown)
        $coords = $this->getCoordinates($propertyData->address ?? '', $propertyData->city ?? '');
        $longitude = (string)($coords['longitude'] ?? '5.2913');
        $latitude = (string)($coords['latitude'] ?? '52.1326');

        // Parse size to get living area
        $livingArea = $this->extractLivingArea($propertyData->size ?? '');

        // Build the data array according to Signal API schema
        // Only include required fields and optional fields we have data for
        $data = [
            // Required fields
            'id' => (string)$property->id,
            'title' => $title,
            'images' => $images,
            'description' => $description,
            'location' => $location,
            'city' => $city,
            'url' => $url,
            'dwellingType' => 'room',
            'propertyType' => 'room',
            'price' => (string)($propertyData->rent ?? '0'),
            'numberOfRooms' => 1,
            'numberOfBedrooms' => 1,
            'numberOfBathrooms' => 1, // Required but not collected - using default
            'numberOfFloors' => 1, // Required but not collected - using default
            'petsAllowed' => (bool)($propertyData->pets_allowed ?? false),
            'smokingAllowed' => (bool)($propertyData->smoking_allowed ?? false),
            'hasBalcony' => false, // Required but not collected - using default
            'hasGarden' => false, // Required but not collected - using default
            'hasGarage' => false, // Required but not collected - using default
            'hasStorage' => false, // Required but not collected - using default
            'hasParking' => false, // Required but not collected - using default
            'livingArea' => $livingArea,
            'plotArea' => '0', // Required but not collected - using default
            'volume' => '0', // Required but not collected - using default
            'postcode' => $propertyData->postcode ?? '',
            // 'longitude' => $longitude,
            // 'latitude' => $latitude,
            'offeredSince' => $property->created_at->toIso8601String(),

            // Optional fields we have data for
            'country' => 'netherlands',
            'offerType' => 'rent',
            'availabilityStatus' => $this->formatAvailabilityStatus($propertyData->period ?? null) ?? '',
            'rentalAgreement' => $this->formatRentalAgreement($propertyData->period ?? null) ?? '',
            'agentName' => 'Domakin NL',
            'contactPhoneNumber' => Emails::PHONE_NUMBERS['domakin_call_center'],
        ];

        return [
            'type' => self::EVENT_TYPE,
            'data' => [$data],
        ];
    }

    /**
     * Format location string (postcode + city area)
     *
     * @param string $postcode
     * @param string $city
     * @return string
     */
    private function formatLocation(string $postcode, string $city): string
    {
        if (empty($postcode) && empty($city)) {
            return '';
        }

        if (!empty($postcode) && !empty($city)) {
            return $postcode . ' (' . $city . ')';
        }

        return $postcode ?: $city;
    }

    /**
     * Determine dwelling type from title/description
     *
     * @param string|null $title
     * @param string|null $description
     * @return string
     */
    private function determineDwellingType(?string $title, ?string $description): string
    {
        $text = strtolower(($title ?? '') . ' ' . ($description ?? ''));

        if (stripos($text, 'studio') !== false) {
            return 'studio';
        }
        if (stripos($text, 'room') !== false || stripos($text, 'bedroom') !== false) {
            return 'room';
        }
        if (stripos($text, 'flat') !== false || stripos($text, 'apartment') !== false) {
            return 'flat';
        }
        if (stripos($text, 'house') !== false) {
            return 'house';
        }

        return 'room'; // Default
    }

    /**
     * Determine property type from title/description
     *
     * @param string|null $title
     * @param string|null $description
     * @return string
     */
    private function determinePropertyType(?string $title, ?string $description): string
    {
        $text = strtolower(($title ?? '') . ' ' . ($description ?? ''));

        if (stripos($text, 'studio') !== false) {
            return 'Studio';
        }
        if (stripos($text, 'room') !== false) {
            return 'Room';
        }
        if (stripos($text, 'apartment') !== false) {
            return 'Apartment';
        }
        if (stripos($text, 'house') !== false) {
            return 'Family home';
        }

        return 'Room'; // Default
    }

    /**
     * Extract living area from size string
     *
     * @param string $size
     * @return string
     */
    private function extractLivingArea(string $size): string
    {
        // Try to extract m² value from size string
        if (preg_match('/(\d+(?:\.\d+)?)\s*m[²2]/i', $size, $matches)) {
            return $matches[1];
        }

        // Try to extract just numbers
        if (preg_match('/(\d+(?:\.\d+)?)/', $size, $matches)) {
            return $matches[1];
        }

        return '0';
    }

    /**
     * Extract number of rooms from size string
     *
     * @param string $size
     * @return int
     */
    private function extractNumberOfRooms(string $size): int
    {
        // Try to find room count in size string
        if (preg_match('/(\d+)\s*room/i', $size, $matches)) {
            return (int)$matches[1];
        }

        return 1; // Default
    }

    /**
     * Extract number of bedrooms from size string
     *
     * @param string $size
     * @return int
     */
    private function extractNumberOfBedrooms(string $size): int
    {
        // Try to find bedroom count
        if (preg_match('/(\d+)\s*bedroom/i', $size, $matches)) {
            return (int)$matches[1];
        }

        // Fallback to room count
        return $this->extractNumberOfRooms($size);
    }

    /**
     * Format availability status from period data
     *
     * @param mixed $period
     * @return string|null
     */
    private function formatAvailabilityStatus($period): ?string
    {
        if (empty($period)) {
            return 'In consultation';
        }

        $periodText = is_array($period) ? ($period['en'] ?? reset($period)) : $period;
        $periodText = strtolower($periodText ?? '');

        if (stripos($periodText, 'immediately') !== false || stripos($periodText, 'asap') !== false) {
            return 'Immediately';
        }

        // Try to extract date
        if (preg_match('/(\d{1,2})[-\/](\d{1,2})[-\/](\d{2,4})/', $periodText, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = strlen($matches[3]) === 2 ? '20' . $matches[3] : $matches[3];
            return "From {$day}-{$month}-{$year}";
        }

        return 'In consultation';
    }

    /**
     * Format rental agreement from period data
     *
     * @param mixed $period
     * @return string|null
     */
    private function formatRentalAgreement($period): ?string
    {
        if (empty($period)) {
            return null;
        }

        $periodText = is_array($period) ? ($period['en'] ?? reset($period)) : $period;
        $periodText = strtolower($periodText ?? '');

        if (stripos($periodText, 'temporary') !== false) {
            return 'Temporary rental';
        }

        if (stripos($periodText, 'unlimited') !== false || stripos($periodText, 'indefinite') !== false) {
            return 'Unlimited period';
        }

        // Try to extract minimum period
        if (preg_match('/(\d+)\s*month/i', $periodText, $matches)) {
            $months = (int)$matches[1];
            return "Contract model A, minimum {$months} months";
        }

        return null;
    }

    /**
     * Get coordinates for address (simplified - you may want to use a geocoding service)
     *
     * @param string $address
     * @param string $city
     * @return array
     */
    private function getCoordinates(string $address, string $city): array
    {
        // Default coordinates for Netherlands center
        // In production, you should use a geocoding service like Google Maps Geocoding API
        $defaultCoordinates = [
            'longitude' => '5.2913',
            'latitude' => '52.1326',
        ];

        // For now, return default coordinates
        // TODO: Implement geocoding service integration
        return $defaultCoordinates;
    }

    /**
     * Decode JSON strings to arrays when applicable
     *
     * @param mixed $value
     * @return mixed
     */
    private function decodeJsonIfNeeded($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }
}
