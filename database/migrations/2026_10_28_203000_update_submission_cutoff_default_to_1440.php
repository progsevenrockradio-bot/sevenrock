<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_programs')) {
            // Asigna el valor predeterminado de 90 minutos únicamente a registros sin margen definido (NULL),
            // preservando estrictamente cualquier margen particular ya configurado por programa (ej. 1440 min / 24h, 120 min, etc.).
            DB::table('master_programs')
                ->whereNull('submission_cutoff_minutes')
                ->update(['submission_cutoff_minutes' => 90]);
        }
    }

    public function down(): void
    {
    }
};
