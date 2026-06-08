<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('band_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('biography')->nullable();
            $table->text('editorial_summary')->nullable();
            $table->string('image_path')->nullable();
            $table->json('featured_facts')->nullable();
            $table->json('milestones')->nullable();
            $table->json('related_artists')->nullable();
            $table->json('official_links')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('source')->nullable()->default('Seven Rock Radio');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('band_profiles');
    }
};
