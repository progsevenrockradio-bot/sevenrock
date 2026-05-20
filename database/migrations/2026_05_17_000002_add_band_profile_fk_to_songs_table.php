<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('songs') || ! Schema::hasColumn('songs', 'band_profile_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $constraintExists = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'songs')
            ->where('constraint_name', 'songs_band_profile_id_foreign')
            ->exists();

        if (! $constraintExists) {
            DB::statement(
                'ALTER TABLE songs ADD CONSTRAINT songs_band_profile_id_foreign FOREIGN KEY (band_profile_id) REFERENCES band_profiles(id) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('songs')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $constraintExists = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'songs')
            ->where('constraint_name', 'songs_band_profile_id_foreign')
            ->exists();

        if ($constraintExists) {
            DB::statement('ALTER TABLE songs DROP FOREIGN KEY songs_band_profile_id_foreign');
        }
    }
};
