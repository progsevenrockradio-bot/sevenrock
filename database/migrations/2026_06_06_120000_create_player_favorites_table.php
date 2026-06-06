<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_favorites', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_key', 100);
            $table->string('signature', 255);
            $table->string('title')->nullable();
            $table->string('artist')->nullable();
            $table->string('cover')->nullable();
            $table->timestamps();

            $table->unique(['owner_key', 'signature']);
            $table->index(['owner_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_favorites');
    }
};
