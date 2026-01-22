<?php

namespace App\Jobs;

use App\Constants\Properties;
use App\Constants\Translations;
use App\Models\JobTracking;
use App\Models\Property;
use App\Services\Helpers;
use App\Services\Integrations\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ReformatPropertyDescriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param int $propertyId
     */
    public function __construct(
        public int $propertyId
    ) {
        // Register job tracking when job is created
        $this->registerJobTracking();
    }

    /**
     * Register job tracking record
     */
    private function registerJobTracking(): void
    {
        try {
            JobTracking::create([
                'job_class' => static::class,
                'status' => 'pending',
                'related_entity_type' => 'Property',
                'related_entity_id' => $this->propertyId,
                'attempts' => 0,
                'metadata' => ['property_id' => $this->propertyId],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to register job tracking', [
                'job_class' => static::class,
                'property_id' => $this->propertyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Execute the job.
     *
     * @param OpenAIService $openAIService
     * @return void
     */
    public function handle(OpenAIService $openAIService): void
    {
        try {
            $property = Property::with('propertyData')->find($this->propertyId);

            if (!$property || !$property->propertyData) {
                Log::warning("Property or PropertyData not found for ID: {$this->propertyId}");
                throw new Exception("Property or PropertyData not found for ID: {$this->propertyId}");
            }

            $propertyData = $property->propertyData;
            $description = $propertyData->description;

            // Extract English description if it's stored as JSON
            $englishDescription = $this->extractEnglishDescription($description);

            if (empty($englishDescription)) {
                Log::warning("No English description found for property ID: {$this->propertyId}");
                throw new Exception("No English description found for property ID: {$this->propertyId}");
            }

            // Get supported locales
            $languages = Translations::WEB_SUPPORTED_LOCALES;

            // Call OpenAI service to reformat and translate
            $result = $openAIService->reformatAndTranslateDescription($englishDescription, $languages);

            // Update property data with new translations and slug
            $this->updatePropertyData($property, $result);

            Log::info("Successfully reformatted property description for property ID: {$this->propertyId}");
        } catch (Exception $e) {
            Log::error("Failed to reformat property description for property ID: {$this->propertyId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Extract English description from stored value
     *
     * @param string|null $description
     * @return string
     */
    private function extractEnglishDescription(?string $description): string
    {
        if (empty($description)) {
            return '';
        }

        // Try to decode as JSON first
        $decoded = json_decode($description, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // If it's JSON, get the English version
            return $decoded['en'] ?? $decoded[''] ?? '';
        }

        // If it's not JSON, assume it's already in English
        return $description;
    }

    /**
     * Update property data with OpenAI results
     *
     * @param \App\Models\PropertyData $propertyData
     * @param array $result
     * @return void
     */
    private function updatePropertyData($property, array $result): void
    {
        // Update description - convert array to JSON string
        if (isset($result['description']) && is_array($result['description'])) {
            $property->propertyData->description = json_encode($result['description'], JSON_UNESCAPED_UNICODE);
        }

        // Update title - convert array to JSON string
        if (isset($result['title']) && is_array($result['title'])) {
            $property->propertyData->title = json_encode($result['title'], JSON_UNESCAPED_UNICODE);
        }

        // Update slug on property
        if (isset($result['slug'])) {
            $property->slug = Helpers::sanitizeSlug($this->propertyId + Properties::FRONTEND_PROPERTY_ID_INDEXING . '-' . $result['slug'] . '-' . $property->propertyData->city);
        }

        try {
            // Save propertyData first
            $property->propertyData->save();
            $property->save();
        } catch (Exception $e) {
            Log::error("Failed to save property data for property ID: {$this->propertyId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::error("ReformatPropertyDescriptionJob failed permanently for property ID: {$this->propertyId}", [
            'error' => $exception->getMessage(),
        ]);

        // Update job tracking - the event listener should handle this, but update here as well
        JobTracking::where('job_class', static::class)
            ->where('related_entity_type', 'Property')
            ->where('related_entity_id', $this->propertyId)
            ->where('status', '!=', 'completed')
            ->latest()
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
                'error_trace' => $exception->getTraceAsString(),
            ]);
    }
}
