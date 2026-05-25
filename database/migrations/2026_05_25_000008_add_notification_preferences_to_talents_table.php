<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talents') || Schema::hasColumn('talents', 'notification_preferences')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            $table->json('notification_preferences')->nullable()->after('social_links');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('talents') || ! Schema::hasColumn('talents', 'notification_preferences')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });
    }
};
