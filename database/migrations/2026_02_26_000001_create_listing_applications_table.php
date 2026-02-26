<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('reference_id')->default(DB::raw('gen_random_uuid()'))->unique();
            $table->integer('step')->default(1);
            $table->uuid('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('postcode')->nullable();
            $table->integer('size')->nullable();
            $table->string('rent')->nullable();
            $table->boolean('registration')->nullable();
            $table->jsonb('flatmates')->nullable();
            $table->jsonb('period')->nullable();
            $table->jsonb('description')->nullable();
            $table->text('images')->nullable();
            $table->boolean('pets_allowed')->nullable();
            $table->boolean('smoking_allowed')->nullable();
            $table->date('available_from')->nullable();
            $table->date('available_to')->nullable();
            $table->integer('type')->nullable();
            $table->integer('furnished_type')->nullable();
            $table->text('shared_space')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('toilets')->nullable();
            $table->text('amenities')->nullable();
            $table->bigInteger('deposit')->nullable();
            $table->integer('bills')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_applications');
    }
};
