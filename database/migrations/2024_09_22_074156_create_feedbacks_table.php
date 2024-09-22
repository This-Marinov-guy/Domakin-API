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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('language')->nullable(); // Nullable language field
            $table->boolean('approved')->default(false); // Approved, default is false
            $table->string('name')->default('anonymous'); // Default name is 'anonymous'
            $table->string('content', 200); // Content required, max 200 characters
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
