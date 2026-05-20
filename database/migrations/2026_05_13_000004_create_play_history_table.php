<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('play_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('song_id')->nullable()->constrained('songs')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('radio_programs')->nullOnDelete();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('source')->default('webhook');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_live')->default(false);
            $table->json('metadata')->nullable();
            $table->dateTime('played_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_history');
    }
};
