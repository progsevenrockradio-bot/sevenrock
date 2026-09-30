<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->string('airplay_api_token', 40)->nullable()->after('moderation_require_event');
            $table->boolean('airplay_notices_enabled')->default(true)->after('airplay_api_token');
            $table->string('airplay_notice_hours', 100)->default('08:00')->after('airplay_notices_enabled');
            $table->boolean('airplay_public_page_enabled')->default(false)->after('airplay_notice_hours');
            $table->string('airplay_report_from_email', 190)->default('press.sevenrockradio@gmail.com')->after('airplay_public_page_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'airplay_api_token',
                'airplay_notices_enabled',
                'airplay_notice_hours',
                'airplay_public_page_enabled',
                'airplay_report_from_email',
            ]);
        });
    }
};
