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
            if (! Schema::hasColumn('theme_settings', 'email_auto_publish')) {
                $table->boolean('email_auto_publish')->default(false)->after('site_name');
            }
            if (! Schema::hasColumn('theme_settings', 'gemini_api_key')) {
                $table->string('gemini_api_key')->nullable()->after('email_auto_publish');
            }
            if (! Schema::hasColumn('theme_settings', 'archive_access_key')) {
                $table->string('archive_access_key')->nullable()->after('gemini_api_key');
            }
            if (! Schema::hasColumn('theme_settings', 'archive_secret_key')) {
                $table->string('archive_secret_key')->nullable()->after('archive_access_key');
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
            if (Schema::hasColumn('theme_settings', 'email_auto_publish')) {
                $columns[] = 'email_auto_publish';
            }
            if (Schema::hasColumn('theme_settings', 'gemini_api_key')) {
                $columns[] = 'gemini_api_key';
            }
            if (Schema::hasColumn('theme_settings', 'archive_access_key')) {
                $columns[] = 'archive_access_key';
            }
            if (Schema::hasColumn('theme_settings', 'archive_secret_key')) {
                $columns[] = 'archive_secret_key';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
