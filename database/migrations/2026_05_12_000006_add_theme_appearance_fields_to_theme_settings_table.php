<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->string('hero_video_path')->nullable()->after('home_video_image_path');
            $table->string('hero_video_url')->nullable()->after('hero_video_path');
            $table->boolean('hero_video_disabled')->default(false)->after('hero_video_url');
            $table->string('social_facebook')->nullable()->after('hero_video_disabled');
            $table->string('social_instagram')->nullable()->after('social_facebook');
            $table->string('social_youtube')->nullable()->after('social_instagram');
            $table->string('social_tiktok')->nullable()->after('social_youtube');
            $table->string('social_x')->nullable()->after('social_tiktok');
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'hero_video_path',
                'hero_video_url',
                'hero_video_disabled',
                'social_facebook',
                'social_instagram',
                'social_youtube',
                'social_tiktok',
                'social_x',
            ]);
        });
    }
};
