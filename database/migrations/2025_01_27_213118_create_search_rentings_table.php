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
        Schema::create('search_rentings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('surname');
            $table->string('phone');
            $table->string('email'); 
            $table->integer('people'); 
            $table->date('move_in'); 
            $table->string('period');
            $table->string('registration');
            $table->integer('budget'); 
            $table->string('city');
            $table->string('letter')->nullable(); 
            $table->string('note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_rentings');
    }
};
