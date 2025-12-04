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
        Schema::table('property_data', function (Blueprint $table) {
            $table->string('postcode')->nullable()->after('address');
            $table->boolean('pets_allowed')->nullable()->after('postcode');
            $table->boolean('smoking_allowed')->nullable()->after('pets_allowed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_data', function (Blueprint $table) {
            $table->dropColumn(['postcode', 'pets_allowed', 'smoking_allowed']);
        });
    }
};
