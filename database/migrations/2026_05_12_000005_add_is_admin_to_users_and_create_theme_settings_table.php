<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::create('theme_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name')->default('Seven Rock Radio');
            $table->string('logo_path')->nullable();
            $table->string('background_path')->nullable();
            $table->string('hero_slide_primary_path')->nullable();
            $table->string('hero_slide_secondary_path')->nullable();
            $table->string('home_album_cover_path')->nullable();
            $table->string('home_video_image_path')->nullable();
            $table->string('body_font')->default('Open Sans');
            $table->string('heading_font')->default('Oswald');
            $table->string('accent_color', 32)->default('#c32720');
            $table->string('nav_color', 32)->default('#081a24');
            $table->string('surface_color', 32)->default('#101012');
            $table->string('body_color', 32)->default('#7b7b7b');
            $table->string('heading_color', 32)->default('#dcdcdc');
            $table->string('line_color', 32)->default('#757575');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }
};
