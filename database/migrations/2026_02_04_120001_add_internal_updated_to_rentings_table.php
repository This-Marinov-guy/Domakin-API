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
        Schema::table('rentings', function (Blueprint $table) {
            $table->timestamp('internal_updated_at')->nullable()->after('internal_note');
            $table->uuid('internal_updated_by')->nullable()->after('internal_updated_at');

            $table->foreign('internal_updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rentings', function (Blueprint $table) {
            $table->dropForeign(['internal_updated_by']);
            $table->dropColumn(['internal_updated_at', 'internal_updated_by']);
        });
    }
};
