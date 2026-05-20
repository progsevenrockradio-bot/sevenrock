<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('albums')) {
            Schema::create('albums', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('artist')->nullable();
                $table->string('cover_image')->nullable();
                $table->text('summary')->nullable();
                $table->date('released_at')->nullable();
                $table->json('tracks')->nullable();
                $table->json('buy_links')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('videos')) {
            Schema::create('videos', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('image')->nullable();
                $table->string('youtube_url')->nullable();
                $table->text('summary')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gallery_images')) {
            Schema::create('gallery_images', function (Blueprint $table): void {
                $table->id();
                $table->string('image');
                $table->string('caption')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->string('location')->nullable();
                $table->string('venue')->nullable();
                $table->string('ticket_url')->nullable();
                $table->string('ticket_label')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('image')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('regular_price', 10, 2)->nullable();
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_sale')->default(false);
                $table->boolean('is_published')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('events');
        Schema::dropIfExists('gallery_images');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('albums');
    }
};
