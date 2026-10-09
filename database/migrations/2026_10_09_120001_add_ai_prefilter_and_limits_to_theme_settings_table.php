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
            if (!Schema::hasColumn('theme_settings', 'ai_daily_max_calls')) {
                $table->unsignedInteger('ai_daily_max_calls')->default(30)->after('ai_provider_chain');
            }
            if (!Schema::hasColumn('theme_settings', 'ai_prefilter_enabled')) {
                $table->boolean('ai_prefilter_enabled')->default(true)->after('ai_daily_max_calls');
            }
            if (!Schema::hasColumn('theme_settings', 'ai_prefilter_keywords')) {
                $table->text('ai_prefilter_keywords')->nullable()->after('ai_prefilter_enabled');
            }
            if (!Schema::hasColumn('theme_settings', 'ai_prefilter_promo_domains')) {
                $table->text('ai_prefilter_promo_domains')->nullable()->after('ai_prefilter_keywords');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            if (Schema::hasColumn('theme_settings', 'ai_prefilter_promo_domains')) {
                $table->dropColumn('ai_prefilter_promo_domains');
            }
            if (Schema::hasColumn('theme_settings', 'ai_prefilter_keywords')) {
                $table->dropColumn('ai_prefilter_keywords');
            }
            if (Schema::hasColumn('theme_settings', 'ai_prefilter_enabled')) {
                $table->dropColumn('ai_prefilter_enabled');
            }
            if (Schema::hasColumn('theme_settings', 'ai_daily_max_calls')) {
                $table->dropColumn('ai_daily_max_calls');
            }
        });
    }
};
