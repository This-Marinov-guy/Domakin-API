<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_applications', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->after('bills');
        });
    }

    public function down(): void
    {
        Schema::table('listing_applications', function (Blueprint $table) {
            $table->dropColumn('referral_code');
        });
    }
};
