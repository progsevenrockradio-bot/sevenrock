<?php

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
        Schema::table('theme_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('theme_settings', 'email_min_importance')) {
                $table->integer('email_min_importance')->default(1)->after('email_processing_enabled');
            }
            if (! Schema::hasColumn('theme_settings', 'email_whitelist_senders')) {
                $table->text('email_whitelist_senders')->nullable()->after('email_min_importance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            if (Schema::hasColumn('theme_settings', 'email_min_importance')) {
                $table->dropColumn('email_min_importance');
            }
            if (Schema::hasColumn('theme_settings', 'email_whitelist_senders')) {
                $table->dropColumn('email_whitelist_senders');
            }
        });
    }
};
