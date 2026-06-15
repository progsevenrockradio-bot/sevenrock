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
        Schema::create('track_submissions', function (Blueprint $table) {
            $table->id();
            
            // Información de la Banda y Contacto
            $table->string('band_name')->index();
            $table->string('song_title');
            $table->string('contact_email')->index();
            $table->string('social_link')->nullable(); // Ej: Instagram o Linktree
            
            // Ruta del Archivo en Cloudflare R2
            $table->string('file_path'); 
            
            // Estado interno para el equipo de A&R de la radio
            $table->string('status')->default('pending')->index(); // pending, approved, rejected
            
            $table->timestamps();
            $table->softDeletes(); // Opcional, pero recomendado por si borras por error
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_submissions');
    }
};
