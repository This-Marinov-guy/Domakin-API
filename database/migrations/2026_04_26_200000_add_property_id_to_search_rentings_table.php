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
        if (Schema::hasColumn('search_rentings', 'property_id')) {
            return;
        }

        Schema::table('search_rentings', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->after('id');
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('search_rentings', 'property_id')) {
            return;
        }

        Schema::table('search_rentings', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
    }
};
