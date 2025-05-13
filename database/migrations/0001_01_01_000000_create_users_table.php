<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()')); // PostgreSQL UUID generation
            $table->string('status')->default(1);
            $table->string('roles')->default('user');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('profile_image')->nullable();
            $table->string('referral_code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
