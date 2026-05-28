<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('radio_programs') || Schema::hasColumn('radio_programs', 'archivo_mp3_disk')) {
            return;
        }

        Schema::table('radio_programs', function (Blueprint $table): void {
            $table->string('archivo_mp3_disk', 20)->nullable()->default('public')->after('archivo_mp3');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('radio_programs') || ! Schema::hasColumn('radio_programs', 'archivo_mp3_disk')) {
            return;
        }

        Schema::table('radio_programs', function (Blueprint $table): void {
            $table->dropColumn('archivo_mp3_disk');
        });
    }
};
