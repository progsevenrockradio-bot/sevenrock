<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('artist');
            $table->string('cover_image');
            $table->text('summary')->nullable();
            $table->date('released_at')->nullable();
            $table->json('tracks')->nullable();
            $table->json('buy_links')->nullable();
            $table->timestamps();

            $table->index('released_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
