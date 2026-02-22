<?php

namespace App\Jobs;

use App\Constants\Properties;
use App\Constants\Translations;
use App\Models\JobTracking;
use App\Models\Property;
use App\Services\PropertyService;
use App\Services\Helpers;
use App\Services\Integrations\GitHubActionsIntegrationService;
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
        public int $propertyId,
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
     * @param GitHubActionsIntegrationService $githubActionsIntegrationService
     * @return void
     */
    public function handle(OpenAIService $openAIService, GitHubActionsIntegrationService $githubActionsIntegrationService, PropertyService $propertyService): void
    {
        try {
            Log::info("[ReformatPropertyDescriptionJob] Starting job execution", [
                'property_id' => $this->propertyId,
            ]);

            $property = Property::with('propertyData')->find($this->propertyId);

            if (!$property || !$property->propertyData) {
                Log::warning("[ReformatPropertyDescriptionJob] Property or PropertyData not found", [
                    'property_id' => $this->propertyId,
                ]);
                throw new Exception("Property or PropertyData not found for ID: {$this->propertyId}");
            }

            $propertyData = $property->propertyData;
            $description = $propertyData->description;

            Log::info("[ReformatPropertyDescriptionJob] Extracting English values", [
                'property_id' => $this->propertyId,
                'has_description' => !empty($description),
                'has_flatmates' => !empty($propertyData->flatmates),
                'has_period' => !empty($propertyData->period),
            ]);

            // Extract English description if it's stored as JSON
            $englishDescription = $this->extractEnglishDescription($description);

            if (empty($englishDescription)) {
                Log::warning("[ReformatPropertyDescriptionJob] No English description found", [
                    'property_id' => $this->propertyId,
                ]);
                throw new Exception("No English description found for property ID: {$this->propertyId}");
            }

            // Extract English values for flatmates and period (size and bills are stored as separate optional fields)
            $englishFlatmates = $this->extractEnglishValue($propertyData->flatmates);
            $englishPeriod = $this->extractEnglishValue($propertyData->period);

            Log::info("[ReformatPropertyDescriptionJob] Extracted English values", [
                'property_id' => $this->propertyId,
                'description_length' => strlen($englishDescription),
                'description_preview' => substr($englishDescription, 0, 100) . '...',
                'flatmates' => $englishFlatmates,
                'period' => $englishPeriod,
            ]);

            // Get supported locales
            $languages = Translations::WEB_SUPPORTED_LOCALES;

            Log::info("[ReformatPropertyDescriptionJob] Calling OpenAI service", [
                'property_id' => $this->propertyId,
                'languages' => $languages,
            ]);

            // Call OpenAI service to reformat and translate
            $result = $openAIService->reformatAndTranslateDescription(
                $englishDescription,
                $languages,
                $englishFlatmates,
                $englishPeriod,
            );

            Log::info("[ReformatPropertyDescriptionJob] OpenAI service returned results", [
                'property_id' => $this->propertyId,
                'has_description' => isset($result['description']),
                'has_title' => isset($result['title']),
                'has_flatmates' => isset($result['flatmates']),
                'has_period' => isset($result['period']),
                'has_slug' => isset($result['slug']),
                'description_languages' => isset($result['description']) ? array_keys($result['description']) : [],
            ]);

            // Update property data with new translations and slug
            $this->updatePropertyData($property, $result, $githubActionsIntegrationService, $propertyService);

            Log::info("[ReformatPropertyDescriptionJob] Successfully completed", [
                'property_id' => $this->propertyId,
            ]);
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
     * Extract English value from stored JSON or string
     *
     * @param string|null $value
     * @return string|null
     */
    private function extractEnglishValue(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Try to decode as JSON first
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // If it's JSON, get the English version
            return $decoded['en'] ?? $decoded[''] ?? null;
        }

        // If it's not JSON, assume it's already in English
        return $value;
    }

    /**
     * Parse size from stored value to a number in square meters.
     * Accepts strings like "15", "15.5", "15 m²", "15m2", "15 sqm".
     *
     * @param string|int|float|null $size
     * @return float|null
     */
    private function parseSizeToSquareMeters(mixed $size): ?float
    {
        if ($size === null || $size === '') {
            return null;
        }

        if (is_numeric($size)) {
            $value = (float) $size;
            return $value > 0 ? $value : null;
        }

        $str = (string) $size;
        if (preg_match('/^\s*([\d.]+)\s*(?:m²|m2|sq\.?\s*m|sqm)?\s*$/ui', trim($str), $m)) {
            $value = (float) $m[1];
            return $value > 0 ? $value : null;
        }

        return null;
    }

    /**
     * Update property data with OpenAI results
     *
     * @param \App\Models\PropertyData $propertyData
     * @param array $result
     * @return void
     */
    private function updatePropertyData($property, array $result, GitHubActionsIntegrationService $githubActionsIntegrationService, PropertyService $propertyService): void
    {
        Log::info("[ReformatPropertyDescriptionJob] Updating property data", [
            'property_id' => $this->propertyId,
        ]);

        // Update description - convert array to JSON string
        if (isset($result['description']) && is_array($result['description'])) {
            $property->propertyData->description = json_encode($result['description'], JSON_UNESCAPED_UNICODE);
            Log::info("[ReformatPropertyDescriptionJob] Updated description", [
                'property_id' => $this->propertyId,
                'languages' => array_keys($result['description']),
            ]);
        }

        // Update title - convert array to JSON string
        if (isset($result['title']) && is_array($result['title'])) {
            $property->propertyData->title = json_encode($result['title'], JSON_UNESCAPED_UNICODE);
            Log::info("[ReformatPropertyDescriptionJob] Updated title", [
                'property_id' => $this->propertyId,
                'languages' => array_keys($result['title']),
            ]);
        }

        // Update flatmates - convert array to JSON string
        if (isset($result['flatmates']) && is_array($result['flatmates'])) {
            $property->propertyData->flatmates = json_encode($result['flatmates'], JSON_UNESCAPED_UNICODE);
            Log::info("[ReformatPropertyDescriptionJob] Updated flatmates", [
                'property_id' => $this->propertyId,
                'languages' => array_keys($result['flatmates']),
            ]);
        }

        // Bills is stored as optional integer; not updated from OpenAI result

        // Update period - convert array to JSON string
        if (isset($result['period']) && is_array($result['period'])) {
            $property->propertyData->period = json_encode($result['period'], JSON_UNESCAPED_UNICODE);
            Log::info("[ReformatPropertyDescriptionJob] Updated period", [
                'property_id' => $this->propertyId,
                'languages' => array_keys($result['period']),
            ]);
        }

        // Update slug on property
        if (isset($result['slug'])) {
            $oldSlug = $property->slug;
            $newSlug = Helpers::sanitizeSlug($this->propertyId + Properties::FRONTEND_PROPERTY_ID_INDEXING . '-' . $result['slug'] . '-' . $property->propertyData->city);
            
            $property->slug = $newSlug;
            $property->link = $propertyService->getPropertyUrl($property->toArray());

            Log::info("[ReformatPropertyDescriptionJob] Updated slug", [
                'property_id' => $this->propertyId,
                'old_slug' => $oldSlug,
                'new_slug' => $property->slug,
                'base_slug' => $result['slug'],
            ]);
        }

        // Update flatmates - convert array to JSON string
        if (isset($result['flatmates']) && is_array($result['flatmates'])) {
            $property->propertyData->flatmates = json_encode($result['flatmates'], JSON_UNESCAPED_UNICODE);
        }

        // Update period - convert array to JSON string
        if (isset($result['period']) && is_array($result['period'])) {
            $property->propertyData->period = json_encode($result['period'], JSON_UNESCAPED_UNICODE);
        }

        try {
            Log::info("[ReformatPropertyDescriptionJob] Saving property data", [
                'property_id' => $this->propertyId,
            ]);
            
            // Save propertyData first
            $property->propertyData->save();
            $property->save();
            
            Log::info("[ReformatPropertyDescriptionJob] Successfully saved property data", [
                'property_id' => $this->propertyId,
            ]);
        } catch (Exception $e) {
            Log::error("[ReformatPropertyDescriptionJob] Failed to save property data", [
                'property_id' => $this->propertyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        try {
            // Trigger GitHub Actions to update sitemap
            $githubActionsIntegrationService->triggerUpdateSitemap();
        } catch (Exception $e) {
            Log::error("Failed to trigger GitHub Actions to update sitemap for property ID: {$this->propertyId}", [
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
