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
        Schema::table('theme_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('theme_settings', 'email_processing_enabled')) {
                $table->boolean('email_processing_enabled')->default(true)->after('email_default_cover_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            if (Schema::hasColumn('theme_settings', 'email_processing_enabled')) {
                $table->dropColumn('email_processing_enabled');
            }
        });
    }
};
