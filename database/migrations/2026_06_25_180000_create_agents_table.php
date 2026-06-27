<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('email')->nullable();
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agents_email_unique ON agents (LOWER(email)) WHERE email IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
