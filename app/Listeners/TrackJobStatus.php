<?php

namespace App\Listeners;

use App\Models\JobTracking;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Log;

class TrackJobStatus
{
    /**
     * Handle job processing event.
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        $job = $event->job;
        $payload = $job->payload();
        $jobClass = $payload['displayName'] ?? $payload['job'] ?? null;

        if (!$jobClass) {
            return;
        }

        // Extract job data from job instance or payload
        $jobData = $this->extractJobData($job, $payload);

        // Find or create tracking record
        $tracking = null;
        
        // First try to find by job_id
        $tracking = JobTracking::where('job_id', $job->getJobId())->first();
        
        // If not found, try to find by related entity
        if (!$tracking && $jobData['related_entity_type'] && $jobData['related_entity_id']) {
            $tracking = JobTracking::where('job_class', $jobClass)
                ->where('related_entity_type', $jobData['related_entity_type'])
                ->where('related_entity_id', $jobData['related_entity_id'])
                ->whereIn('status', ['pending', 'processing'])
                ->first();
        }

        if (!$tracking) {
            $tracking = JobTracking::create([
                'job_id' => $job->getJobId(),
                'job_class' => $jobClass,
                'status' => 'processing',
                'related_entity_type' => $jobData['related_entity_type'],
                'related_entity_id' => $jobData['related_entity_id'],
                'attempts' => 1,
                'started_at' => now(),
                'metadata' => $jobData['metadata'],
            ]);
        } else {
            $tracking->update([
                'job_id' => $job->getJobId(),
                'status' => 'processing',
                'started_at' => now(),
                'attempts' => $tracking->attempts + 1,
            ]);
        }
    }

    /**
     * Handle job processed event.
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        $job = $event->job;
        $tracking = JobTracking::where('job_id', $job->getJobId())->first();

        if ($tracking) {
            $tracking->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Handle job failed event.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        $job = $event->job;
        $exception = $event->exception;

        $tracking = JobTracking::where('job_id', $job->getJobId())->first();

        if ($tracking) {
            $tracking->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
                'error_trace' => $exception->getTraceAsString(),
            ]);
        } else {
            // If tracking doesn't exist, try to create it from the job payload
            $payload = $job->payload();
            $jobClass = $payload['displayName'] ?? $payload['job'] ?? null;

            if ($jobClass) {
                $jobData = $this->extractJobData($job, $payload);
                JobTracking::create([
                    'job_id' => $job->getJobId(),
                    'job_class' => $jobClass,
                    'status' => 'failed',
                    'related_entity_type' => $jobData['related_entity_type'],
                    'related_entity_id' => $jobData['related_entity_id'],
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                    'error_trace' => $exception->getTraceAsString(),
                    'metadata' => $jobData['metadata'],
                ]);
            }
        }
    }

    /**
     * Handle job exception occurred event.
     */
    public function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        // This is called before JobFailed, so we can update the tracking
        $job = $event->job;
        $exception = $event->exception;

        $tracking = JobTracking::where('job_id', $job->getJobId())->first();

        if ($tracking) {
            $tracking->update([
                'error_message' => $exception->getMessage(),
                'error_trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extract job data from payload or job instance
     */
    private function extractJobData($job, ?array $payload = null): array
    {
        $data = [
            'related_entity_type' => null,
            'related_entity_id' => null,
            'metadata' => [],
        ];

        // Try to get data from job instance (for unserialized jobs)
        if (is_object($job) && method_exists($job, 'getResolvedJob')) {
            $resolvedJob = $job->getResolvedJob();
            if ($resolvedJob && isset($resolvedJob->propertyId)) {
                $data['related_entity_type'] = 'Property';
                $data['related_entity_id'] = $resolvedJob->propertyId;
                $data['metadata'] = ['property_id' => $resolvedJob->propertyId];
                return $data;
            }
        }

        // Try to extract from payload if provided
        if ($payload) {
            // Try to extract data from job command
            if (isset($payload['data']['command'])) {
                try {
                    $command = unserialize($payload['data']['command']);
                    
                    // For jobs with propertyId or similar
                    if (isset($command->propertyId)) {
                        $data['related_entity_type'] = 'Property';
                        $data['related_entity_id'] = $command->propertyId;
                        $data['metadata'] = ['property_id' => $command->propertyId];
                        return $data;
                    }
                } catch (\Exception $e) {
                    // Ignore unserialize errors
                }
            }

            // Try to extract from data directly
            if (isset($payload['data']['propertyId'])) {
                $data['related_entity_type'] = 'Property';
                $data['related_entity_id'] = $payload['data']['propertyId'];
                $data['metadata'] = ['property_id' => $payload['data']['propertyId']];
            }
        }

        return $data;
    }
}
