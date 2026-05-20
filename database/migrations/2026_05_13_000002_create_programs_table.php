<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radio_programs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('numero_episodio')->nullable();
            $table->string('titulo_programa');
            $table->unsignedBigInteger('master_program_id')->nullable();
            $table->string('dia_transmision')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('conductor');
            $table->date('fecha_emision')->nullable();
            $table->text('biografia_invitado')->nullable();
            $table->string('archivo_mp3')->nullable();
            $table->boolean('enviado_radioboss')->default(false);
            $table->boolean('sync_archive_org')->default(true);
            $table->string('archive_org_status', 32)->nullable();
            $table->string('archive_org_remote_path')->nullable();
            $table->timestamp('archive_org_uploaded_at')->nullable();
            $table->text('archive_org_last_error')->nullable();
            $table->longText('archive_org_metadata')->nullable();
            $table->text('informacion_fija_programa')->nullable();
            $table->string('genero_musical')->default('HARD ROCK');
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('caratula_programa')->nullable();
            $table->string('imagen_episodio')->nullable();
            $table->string('imagen_invitado')->nullable();
            $table->string('ruta_ftp_radioboss')->nullable();
            $table->text('resena')->nullable();
            $table->string('live_title')->nullable();
            $table->longText('live_description')->nullable();
            $table->string('live_image_url')->nullable();
            $table->longText('live_news_ids')->nullable();
            $table->longText('preview_news_ids')->nullable();
            $table->text('comentario_episodio')->nullable();
            $table->string('email_notificacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_programs');
    }
};
