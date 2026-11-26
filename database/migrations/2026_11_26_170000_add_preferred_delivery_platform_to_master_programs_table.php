<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_programs') && ! Schema::hasColumn('master_programs', 'preferred_delivery_platform')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                $table->string('preferred_delivery_platform', 40)
                    ->nullable()
                    ->after('recognize_delivery_from_notification_email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('master_programs') && Schema::hasColumn('master_programs', 'preferred_delivery_platform')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                $table->dropColumn('preferred_delivery_platform');
            });
        }
    }
};
