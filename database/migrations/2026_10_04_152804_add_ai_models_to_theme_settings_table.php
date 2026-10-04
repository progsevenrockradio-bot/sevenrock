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
            if (!Schema::hasColumn('theme_settings', 'ai_gemini_model')) {
                $table->string('ai_gemini_model', 80)->nullable()->default('gemini-flash-latest')->after('gemini_api_key');
            }
            if (!Schema::hasColumn('theme_settings', 'ai_openrouter_model')) {
                $table->string('ai_openrouter_model', 80)->nullable()->default('openrouter/free')->after('openrouter_api_key');
            }
            if (!Schema::hasColumn('theme_settings', 'ai_provider_chain')) {
                $table->string('ai_provider_chain', 100)->nullable()->default('gemini,openrouter')->after('email_whitelist_senders');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            if (Schema::hasColumn('theme_settings', 'ai_gemini_model')) {
                $table->dropColumn('ai_gemini_model');
            }
            if (Schema::hasColumn('theme_settings', 'ai_openrouter_model')) {
                $table->dropColumn('ai_openrouter_model');
            }
            // we don't drop ai_provider_chain because it might have existed before
        });
    }
};
