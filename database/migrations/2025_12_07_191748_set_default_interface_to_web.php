<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all null interface values to 'web' for existing records
        DB::table('rentings')
            ->whereNull('interface')
            ->update(['interface' => 'web']);

        DB::table('search_rentings')
            ->whereNull('interface')
            ->update(['interface' => 'web']);

        DB::table('viewings')
            ->whereNull('interface')
            ->update(['interface' => 'web']);

        DB::table('properties')
            ->whereNull('interface')
            ->update(['interface' => 'web']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set interface back to null for records that were updated from null to 'web'
        // Note: This is a best-effort reversal, as we can't distinguish which records
        // were originally null vs those that were already 'web'
        DB::table('rentings')
            ->where('interface', 'web')
            ->update(['interface' => null]);

        DB::table('search_rentings')
            ->where('interface', 'web')
            ->update(['interface' => null]);

        DB::table('viewings')
            ->where('interface', 'web')
            ->update(['interface' => null]);

        DB::table('properties')
            ->where('interface', 'web')
            ->update(['interface' => null]);
    }
};
