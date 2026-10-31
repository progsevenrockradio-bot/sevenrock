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
            if (!Schema::hasColumn('master_programs', 'pais')) {
                $table->string('pais', 100)->nullable()->after('conductor');
            }
        });

        Schema::table('radio_programs', function (Blueprint $table) {
            if (!Schema::hasColumn('radio_programs', 'pais')) {
                $table->string('pais', 100)->nullable()->after('conductor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            if (Schema::hasColumn('master_programs', 'pais')) {
                $table->dropColumn('pais');
            }
        });

        Schema::table('radio_programs', function (Blueprint $table) {
            if (Schema::hasColumn('radio_programs', 'pais')) {
                $table->dropColumn('pais');
            }
        });
    }
};
