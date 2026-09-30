<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // airplay_weeks
        Schema::create('airplay_weeks', function (Blueprint ): void {
            ->id();
            ->string('semana', 10)->unique();
            ->dateTime('generado_en')->nullable();
            ->dateTime('recibido_en')->nullable();
            ->unsignedInteger('pistas_total')->default(0);
            ->unsignedInteger('novedades')->default(0);
            ->unsignedSmallInteger('hueco_publicidad_min')->default(8);
            ->enum('estado', ['recibida', 'revisada', 'publicada'])->default('recibida');
            ->timestamps();
        });

        // airplay_schedule
        Schema::create('airplay_schedule', function (Blueprint ): void {
            ->id();
            ->string('semana', 10);
            ->unsignedTinyInteger('dia');
            ->unsignedTinyInteger('hora');
            ->unsignedSmallInteger('posicion');
            ->string('artista', 190);
            ->string('titulo', 190);
            ->string('album', 190)->nullable();
            ->string('genero', 100)->nullable();
            ->string('categoria', 100)->nullable();
            ->string('tipo_item', 50)->default('musica');
            ->unsignedMediumInteger('duracion_seg')->nullable();
            ->string('duracion_fmt', 10)->nullable();
            ->boolean('es_novedad')->default(false);
            ->boolean('es_primer_pase')->default(false);
            ->string('sello', 190)->nullable();
            ->string('email_sello', 190)->nullable();
            ->string('email_artista', 190)->nullable();
            ->string('contacto_email', 190)->nullable();
            ->string('spotify_track_id', 100)->nullable();
            ->string('isrc', 50)->nullable();
            ->foreignId('talent_id')->nullable()->constrained('talents')->nullOnDelete();
            ->string('sha1', 40)->nullable();
            ->dateTime('notificado_at')->nullable();
            ->unsignedSmallInteger('avisos_enviados')->default(0);
            ->timestamp('created_at')->useCurrent();
            ->unique(['semana', 'dia', 'hora', 'posicion'], 'airplay_schedule_unique_slot');
            ->index('artista');
            ->index('sha1');
            ->index('notificado_at');
            ->index('semana');
            ->index('es_primer_pase');
        });

        // airplay_notices
        Schema::create('airplay_notices', function (Blueprint ): void {
            ->id();
            ->foreignId('airplay_schedule_id')->constrained('airplay_schedule')->cascadeOnDelete();
            ->string('email', 190);
            ->enum('tipo', ['productora', 'artista']);
            ->dateTime('enviado_en')->nullable();
            ->string('estado', 50)->default('pendiente');
            ->text('error')->nullable();
            ->unique(['airplay_schedule_id', 'email'], 'airplay_notices_unique_recipient');
            ->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airplay_notices');
        Schema::dropIfExists('airplay_schedule');
        Schema::dropIfExists('airplay_weeks');
    }
};
