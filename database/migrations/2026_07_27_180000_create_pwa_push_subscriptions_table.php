<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla para almacenar suscripciones Web Push de la PWA.
 * Cada registro corresponde a un dispositivo/navegador que activó
 * las notificaciones push en la aplicación móvil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pwa_push_subscriptions', function (Blueprint $table): void {
            $table->id();

            // El endpoint único del navegador (URL de Push Service)
            $table->text('endpoint')->unique();

            // Clave pública p256dh del cliente
            $table->text('p256dh');

            // Secreto de autenticación del cliente
            $table->text('auth');

            // Info extra (opcional, para depuración)
            $table->string('user_agent', 512)->nullable();

            // Timestamps de creación/actualización
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pwa_push_subscriptions');
    }
};
