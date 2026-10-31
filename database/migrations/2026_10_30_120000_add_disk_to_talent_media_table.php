<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('talent_media') && ! Schema::hasColumn('talent_media', 'disk')) {
            Schema::table('talent_media', function (Blueprint $table): void {
                $table->string('disk', 32)->nullable()->after('filename');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('talent_media') && Schema::hasColumn('talent_media', 'disk')) {
            Schema::table('talent_media', function (Blueprint $table): void {
                $table->dropColumn('disk');
            });
        }
    }
};

