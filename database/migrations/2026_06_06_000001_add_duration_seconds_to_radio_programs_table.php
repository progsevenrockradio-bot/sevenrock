<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('radio_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('radio_programs', 'duration_seconds')) {
                $table->unsignedInteger('duration_seconds')->nullable()->after('hora_fin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('radio_programs', function (Blueprint $table): void {
            if (Schema::hasColumn('radio_programs', 'duration_seconds')) {
                $table->dropColumn('duration_seconds');
            }
        });
    }
};
