<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_data', function (Blueprint $table) {
            $table->text('payment_link')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('property_data', function (Blueprint $table) {
            $table->dropColumn('payment_link');
        });
    }
};


