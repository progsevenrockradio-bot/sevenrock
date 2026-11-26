<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_programs') && ! Schema::hasColumn('master_programs', 'delivery_recognition_aliases')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                $table->text('delivery_recognition_aliases')
                    ->nullable()
                    ->after('preferred_delivery_platform');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('master_programs') && Schema::hasColumn('master_programs', 'delivery_recognition_aliases')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                $table->dropColumn('delivery_recognition_aliases');
            });
        }
    }
};
