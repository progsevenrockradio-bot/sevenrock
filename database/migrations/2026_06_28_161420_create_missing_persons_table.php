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
        Schema::create('missing_persons', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('cedula', 20)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('sex', ['masculino', 'femenino', 'otro'])->nullable();
            $table->string('place_of_residence')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->string('last_seen_location')->nullable();
            $table->date('missing_since')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->enum('status', ['active', 'found'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_persons');
    }
};
