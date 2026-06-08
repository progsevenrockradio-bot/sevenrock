<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('theme_settings', 'logo_height')) {
            Schema::table('theme_settings', function (Blueprint $table) {
                $table->integer('logo_height')->default(62)->after('logo_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            $table->dropColumn('logo_height');
        });
    }
};
