<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasColumn('properties', 'slug')) {
                $table->string('slug')->nullable()->after('referral_code');
            }
            if (! Schema::hasColumn('properties', 'link')) {
                $table->text('link')->nullable()->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'link')) {
                $table->dropColumn('link');
            }
            if (Schema::hasColumn('properties', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
