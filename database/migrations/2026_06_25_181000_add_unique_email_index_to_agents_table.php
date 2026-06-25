<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS agents_email_index');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agents_email_unique ON agents (LOWER(email)) WHERE email IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agents_email_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS agents_email_index ON agents (email)');
    }
};
