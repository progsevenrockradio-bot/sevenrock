<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('band_profiles')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $hasPrimaryKey = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'band_profiles')
            ->where('index_name', 'PRIMARY')
            ->exists();

        if (! $hasPrimaryKey) {
            DB::statement('ALTER TABLE band_profiles ADD PRIMARY KEY (id)');
        }
    }

    public function down(): void
    {
        // No-op: if the table already has a primary key in a fresh install, we should not remove it.
    }
};
