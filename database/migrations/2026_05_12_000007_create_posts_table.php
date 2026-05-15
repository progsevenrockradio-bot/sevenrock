<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author')->default('admin');
            $table->text('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->text('quote')->nullable();
            $table->string('featured_image')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('published_at');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
