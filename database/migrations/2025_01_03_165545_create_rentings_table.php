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
        Schema::create('rentings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('property');
            $table->string('name');
            $table->string('surname');
            $table->string('phone');
            $table->string(column: 'email');
            $table->string(column: 'letter');
            $table->text(column: 'note')->nullable();
            $table->text('referral_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentings');
    }
};
