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
        Schema::create('property_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properties_id')->constrained()->onDelete('cascade');
            $table->string('city');
            $table->string('address');
            $table->string('size');
            $table->string('period');
            $table->string('rent');
            $table->string('bills');
            $table->string('flatmates');
            $table->string('registration');
            $table->string('description');
            $table->json('images');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_data');
    }
};
