<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_taxonomies');
    }
};
