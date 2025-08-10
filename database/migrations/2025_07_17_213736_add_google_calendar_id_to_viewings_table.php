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
        Schema::table('viewings', function (Blueprint $table) {
            if (!Schema::hasColumn('viewings', 'google_calendar_id')) {
                $table->string('google_calendar_id')->nullable()->after('referral_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viewings', function (Blueprint $table) {
            $table->dropColumn('google_calendar_id');
        });
    }
};
