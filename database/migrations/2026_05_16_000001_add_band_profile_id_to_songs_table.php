<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->foreignId('band_profile_id')
                ->nullable()
                ->after('cover_image')
                ->constrained('band_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('band_profile_id');
        });
    }
};
