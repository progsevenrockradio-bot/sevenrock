<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talents') || Schema::hasColumn('talents', 'payment_links')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            $table->json('payment_links')->nullable()->after('notification_preferences');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('talents') || ! Schema::hasColumn('talents', 'payment_links')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            $table->dropColumn('payment_links');
        });
    }
};
