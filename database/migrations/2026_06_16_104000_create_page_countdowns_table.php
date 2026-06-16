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
        Schema::create('page_countdowns', function (Blueprint $table) {
            $table->id();
            $table->string('route_path')->unique()->comment('Ruta a bloquear, ej. talentos/register');
            $table->string('title')->nullable()->comment('Título público de la página Coming Soon');
            $table->text('description')->nullable()->comment('Descripción para el público');
            $table->dateTime('active_at')->nullable()->comment('Cuándo se activa la página real');
            $table->boolean('is_enabled')->default(true)->comment('Si está activo el contador');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_countdowns');
    }
};
