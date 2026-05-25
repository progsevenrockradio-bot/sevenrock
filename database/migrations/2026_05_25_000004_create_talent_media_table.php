<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talent_media')) {
            return;
        }

        Schema::create('talent_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->enum('type', ['photo', 'mp3', 'document']);
            $table->string('filename');
            $table->string('backblaze_key');
            $table->string('url');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_media');
    }
};
