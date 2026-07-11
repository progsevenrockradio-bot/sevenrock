<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->boolean('sync_archive_org')->default(true)->after('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropColumn('sync_archive_org');
        });
    }
};
