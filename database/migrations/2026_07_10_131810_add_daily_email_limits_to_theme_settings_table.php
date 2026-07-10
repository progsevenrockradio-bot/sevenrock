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
            if (! Schema::hasColumn('theme_settings', 'email_daily_posts_limit')) {
                $table->integer('email_daily_posts_limit')->default(3)->after('email_auto_publish');
            }
            if (! Schema::hasColumn('theme_settings', 'email_daily_releases_limit')) {
                $table->integer('email_daily_releases_limit')->default(3)->after('email_daily_posts_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('theme_settings', 'email_daily_posts_limit')) {
                $columns[] = 'email_daily_posts_limit';
            }
            if (Schema::hasColumn('theme_settings', 'email_daily_releases_limit')) {
                $columns[] = 'email_daily_releases_limit';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
