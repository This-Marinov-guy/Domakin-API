<?php

namespace App\Console\Commands;

use App\Jobs\ReformatPropertyDescriptionJob;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReformatPropertyDescription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'property:reformat-description 
                            {id : The property ID to reformat}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reformat and translate property description using OpenAI for a specific property ID';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $propertyId = (int) $this->argument('id');
        $sync = $this->option('sync');

        $this->info("Processing property ID: {$propertyId}");

        // Verify property exists
        $property = Property::with('propertyData')->find($propertyId);

        if (!$property) {
            $this->error("Property with ID {$propertyId} not found.");
            return 1;
        }

        if (!$property->propertyData) {
            $this->error("PropertyData not found for property ID {$propertyId}.");
            return 1;
        }

        $this->info("✓ Property found: ID {$propertyId}");

        if ($sync) {
            // Run synchronously
            $this->info("Running job synchronously...");
            
            try {
                $job = new ReformatPropertyDescriptionJob($propertyId);
                $job->handle(
                    app(\App\Services\Integrations\OpenAIService::class),
                    app(\App\Services\Integrations\GitHubActionsIntegrationService::class)
                );
                
                $this->info("✅ Successfully reformatted property description for property ID: {$propertyId}");
                return 0;
            } catch (\Exception $e) {
                $this->error("❌ Failed to reformat property description: " . $e->getMessage());
                Log::error("ReformatPropertyDescription command failed", [
                    'property_id' => $propertyId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return 1;
            }
        } else {
            // Dispatch to queue
            $this->info("Dispatching job to queue...");
            
            try {
                ReformatPropertyDescriptionJob::dispatch($propertyId);
                $this->info("✅ Job dispatched successfully for property ID: {$propertyId}");
                $this->info("The job will be processed by the queue worker.");
                return 0;
            } catch (\Exception $e) {
                $this->error("❌ Failed to dispatch job: " . $e->getMessage());
                Log::error("Failed to dispatch ReformatPropertyDescriptionJob", [
                    'property_id' => $propertyId,
                    'error' => $e->getMessage(),
                ]);
                return 1;
            }
        }
    }
}
