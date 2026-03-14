<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_bonuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('referral_code');
            $table->integer('amount')->default(100);
            $table->smallInteger('status')->default(1)->comment('1=waiting_approval, 2=pending, 3=completed, 4=rejected');
            $table->smallInteger('type')->comment('1=listing, 2=viewing, 3=renting');
            $table->text('reference_id');
            $table->text('public_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_bonuses');
    }
};
