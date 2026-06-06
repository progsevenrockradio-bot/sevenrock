<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_key', 100);
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('reaction_type', 32)->default('like');
            $table->timestamps();

            $table->unique(['owner_key', 'post_id', 'reaction_type']);
            $table->index(['post_id', 'reaction_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reactions');
    }
};
