<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talent_albums')) {
            return;
        }

        Schema::create('talent_albums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('cover_image')->nullable()->comment('Backblaze key');
            $table->string('cover_url')->nullable();
            $table->date('release_date')->nullable();
            $table->text('description')->nullable();
            $table->json('tracks')->nullable()->comment('Array of {title, duration}');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['talent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_albums');
    }
};
