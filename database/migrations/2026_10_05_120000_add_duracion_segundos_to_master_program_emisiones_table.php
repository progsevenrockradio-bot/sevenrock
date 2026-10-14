<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_program_emisiones')) {
            return;
        }

        Schema::table('master_program_emisiones', function (Blueprint $table): void {
            if (! Schema::hasColumn('master_program_emisiones', 'duracion_segundos')) {
                $table->unsignedInteger('duracion_segundos')->nullable()->after('duracion_minutos');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_program_emisiones')) {
            return;
        }

        Schema::table('master_program_emisiones', function (Blueprint $table): void {
            if (Schema::hasColumn('master_program_emisiones', 'duracion_segundos')) {
                $table->dropColumn('duracion_segundos');
            }
        });
    }
};
