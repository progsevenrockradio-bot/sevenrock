<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // airplay_weeks
        Schema::create('airplay_weeks', function (Blueprint $table): void {
            $table->id();
            $table->string('semana', 10)->unique();
            $table->dateTime('generado_en')->nullable();
            $table->dateTime('recibido_en')->nullable();
            $table->unsignedInteger('pistas_total')->default(0);
            $table->unsignedInteger('novedades')->default(0);
            $table->unsignedSmallInteger('hueco_publicidad_min')->default(8);
            $table->enum('estado', ['recibida', 'revisada', 'publicada'])->default('recibida');
            $table->timestamps();
        });

        // airplay_schedule
        Schema::create('airplay_schedule', function (Blueprint $table): void {
            $table->id();
            $table->string('semana', 10);
            $table->unsignedTinyInteger('dia');
            $table->unsignedTinyInteger('hora');
            $table->unsignedSmallInteger('posicion');
            $table->string('artista', 190);
            $table->string('titulo', 190);
            $table->string('album', 190)->nullable();
            $table->string('genero', 100)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->string('tipo_item', 50)->default('musica');
            $table->unsignedMediumInteger('duracion_seg')->nullable();
            $table->string('duracion_fmt', 10)->nullable();
            $table->boolean('es_novedad')->default(false);
            $table->boolean('es_primer_pase')->default(false);
            $table->string('sello', 190)->nullable();
            $table->string('email_sello', 190)->nullable();
            $table->string('email_artista', 190)->nullable();
            $table->string('contacto_email', 190)->nullable();
            $table->string('spotify_track_id', 100)->nullable();
            $table->string('isrc', 50)->nullable();
            $table->foreignId('talent_id')->nullable()->constrained('talents')->nullOnDelete();
            $table->string('sha1', 40)->nullable();
            $table->dateTime('notificado_at')->nullable();
            $table->unsignedSmallInteger('avisos_enviados')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['semana', 'dia', 'hora', 'posicion'], 'airplay_schedule_unique_slot');
            $table->index('artista');
            $table->index('sha1');
            $table->index('notificado_at');
            $table->index('semana');
            $table->index('es_primer_pase');
        });

        // airplay_notices
        Schema::create('airplay_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('airplay_schedule_id')->constrained('airplay_schedule')->cascadeOnDelete();
            $table->string('email', 190);
            $table->enum('tipo', ['productora', 'artista']);
            $table->dateTime('enviado_en')->nullable();
            $table->string('estado', 50)->default('pendiente');
            $table->text('error')->nullable();
            $table->unique(['airplay_schedule_id', 'email'], 'airplay_notices_unique_recipient');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airplay_notices');
        Schema::dropIfExists('airplay_schedule');
        Schema::dropIfExists('airplay_weeks');
    }
};
