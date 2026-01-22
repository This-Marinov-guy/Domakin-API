<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create job_tracking table to track job status
        Schema::create('job_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->nullable()->index(); // Laravel job ID from jobs table
            $table->string('job_class'); // Full class name of the job
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('related_entity_type')->nullable(); // e.g., 'Property', 'User', etc.
            $table->unsignedBigInteger('related_entity_id')->nullable(); // ID of the related entity
            $table->unsignedInteger('attempts')->default(0); // Current attempt count
            $table->text('error_message')->nullable(); // Last error message if failed
            $table->text('error_trace')->nullable(); // Error trace for debugging
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata about the job
            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index('job_class');
            $table->index(['related_entity_type', 'related_entity_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_tracking');
    }
};
