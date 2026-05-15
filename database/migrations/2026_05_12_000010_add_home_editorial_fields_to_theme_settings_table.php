<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->json('featured_stories')->nullable()->after('contact_phone_secondary');
            $table->json('latest_podcasts')->nullable()->after('featured_stories');
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->dropColumn(['featured_stories', 'latest_podcasts']);
        });
    }
};
