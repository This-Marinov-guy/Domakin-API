<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobTracking extends Model
{
    protected $table = 'job_tracking';

    protected $fillable = [
        'job_id',
        'job_class',
        'status',
        'related_entity_type',
        'related_entity_id',
        'attempts',
        'error_message',
        'error_trace',
        'started_at',
        'completed_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by job class
     */
    public function scopeJobClass($query, string $jobClass)
    {
        return $query->where('job_class', $jobClass);
    }

    /**
     * Scope to filter by related entity
     */
    public function scopeRelatedEntity($query, string $type, int $id)
    {
        return $query->where('related_entity_type', $type)
            ->where('related_entity_id', $id);
    }

    /**
     * Scope to get pending jobs
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get processing jobs
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to get completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
