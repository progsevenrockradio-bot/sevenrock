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
        Schema::dropIfExists('new_releases');

        Schema::create('new_releases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('artist_name');
            $table->unsignedBigInteger('radio_artist_id')->nullable();
            $table->date('released_at')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('spotify_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('released_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('new_releases');
    }
};
