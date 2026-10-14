<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_program_emisiones')) {
            return;
        }

        Schema::create('master_program_emisiones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('master_program_id');
            $table->foreign('master_program_id')
                ->references('id')
                ->on('master_programs')
                ->cascadeOnDelete();

            $table->string('tipo', 20)->default('normal');
            $table->string('etiqueta')->nullable();
            $table->string('dia_semana', 12);
            $table->time('hora_inicio');
            $table->unsignedInteger('duracion_minutos')->default(120);
            $table->string('enlace')->nullable();
            $table->string('url_podcast')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('master_program_id');
            $table->index('dia_semana');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_program_emisiones');
    }
};
