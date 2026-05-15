<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('artist');
            $table->string('album')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('cover_image')->nullable();
            $table->longText('lyrics')->nullable();
            $table->longText('band_info')->nullable();
            $table->json('band_members')->nullable();
            $table->json('social_links')->nullable();
            $table->foreignId('program_id')->nullable();
            $table->boolean('is_live')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->unsignedInteger('play_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
