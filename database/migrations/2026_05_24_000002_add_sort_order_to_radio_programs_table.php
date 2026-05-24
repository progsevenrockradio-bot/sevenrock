<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('radio_programs') || Schema::hasColumn('radio_programs', 'sort_order')) {
            return;
        }

        Schema::table('radio_programs', function (Blueprint $table): void {
            $table->integer('sort_order')->default(0)->after('numero_episodio');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('radio_programs') || ! Schema::hasColumn('radio_programs', 'sort_order')) {
            return;
        }

        Schema::table('radio_programs', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
