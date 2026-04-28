<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viewings', function (Blueprint $table) {
            if (!Schema::hasColumn('viewings', 'internal_note')) {
                $table->text('internal_note')->nullable()->after('status');
            }

            if (!Schema::hasColumn('viewings', 'internal_updated_at')) {
                $table->timestamp('internal_updated_at')->nullable()->after('internal_note');
            }

            if (!Schema::hasColumn('viewings', 'internal_updated_by')) {
                $table->uuid('internal_updated_by')->nullable()->after('internal_updated_at');
                $table->foreign('internal_updated_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('viewings', function (Blueprint $table) {
            if (Schema::hasColumn('viewings', 'internal_updated_by')) {
                $table->dropForeign(['internal_updated_by']);
                $table->dropColumn('internal_updated_by');
            }

            if (Schema::hasColumn('viewings', 'internal_updated_at')) {
                $table->dropColumn('internal_updated_at');
            }

            if (Schema::hasColumn('viewings', 'internal_note')) {
                $table->dropColumn('internal_note');
            }
        });
    }
};
