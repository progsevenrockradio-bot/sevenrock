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
            if (! Schema::hasColumn('theme_settings', 'email_daily_news_limit')) {
                $table->integer('email_daily_news_limit')->default(50)->after('email_daily_releases_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            if (Schema::hasColumn('theme_settings', 'email_daily_news_limit')) {
                $table->dropColumn('email_daily_news_limit');
            }
        });
    }
};
