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
            $table->string('openrouter_api_key')->nullable()->after('gemini_api_key');
            $table->boolean('ai_fallback_enabled')->default(1)->after('openrouter_api_key');
            $table->string('ai_provider_chain', 100)->default('gemini,openrouter')->after('ai_fallback_enabled');
        });

        Schema::table('processed_emails', function (Blueprint $table) {
            $table->integer('attempts')->default(0)->after('status');
            $table->string('last_error', 500)->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('processed_emails', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_error']);
        });

        Schema::table('theme_settings', function (Blueprint $table) {
            $table->dropColumn(['openrouter_api_key', 'ai_fallback_enabled', 'ai_provider_chain']);
        });
    }
};
