<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_programs')) {
            return;
        }

        Schema::create('master_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('conductor');
            $table->enum('dia_transmision', ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO']);
            $table->string('hora_transmision', 8)->nullable();
            $table->string('timezone')->default('America/Caracas');
            $table->unsignedInteger('duracion_minutos')->default(120);
            $table->string('genero');
            $table->string('caratula_url')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('live_title')->nullable();
            $table->longText('live_description')->nullable();
            $table->string('live_image_url')->nullable();
            $table->timestamp('live_starts_at')->nullable();
            $table->timestamp('live_ends_at')->nullable();
            $table->json('default_news_ids')->nullable();
            $table->json('live_news_ids')->nullable();
            $table->json('preview_news_ids')->nullable();
            $table->string('comentario_predeterminado')->nullable();
            $table->string('red_social1_url')->nullable();
            $table->string('red_social2_url')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('archive_identifier')->nullable();
            $table->unsignedInteger('vistas_archive')->default(0);
            $table->unsignedInteger('escuchas_locales')->default(0);
            $table->unsignedInteger('vistas_totales')->default(0);
            $table->timestamp('stats_updated_at')->nullable();
            $table->string('ruta_ftp')->nullable();
            $table->string('email_notificacion')->nullable();
            $table->string('email_copia_notificacion')->nullable();
            $table->timestamps();

            $table->index(['activo', 'dia_transmision']);
            $table->index(['dia_transmision', 'hora_transmision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_programs');
    }
};
