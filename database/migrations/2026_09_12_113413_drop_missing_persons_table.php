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
        Schema::dropIfExists('missing_persons');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('missing_persons', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('cedula')->nullable()->unique();
            $table->integer('age')->nullable();
            $table->string('sex')->nullable();
            $table->string('place_of_residence')->nullable();
            $table->string('hospital_admitted_to')->nullable();
            $table->string('date_update')->nullable();
            $table->string('service_provided')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->enum('status', ['active', 'found', 'inactive'])->default('active');
            $table->timestamps();
        });
    }
};
