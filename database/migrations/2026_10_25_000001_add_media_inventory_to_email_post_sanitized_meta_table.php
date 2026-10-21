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
        if (Schema::hasTable('email_post_sanitized_meta')) {
            Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
                if (!Schema::hasColumn('email_post_sanitized_meta', 'media_inventory')) {
                    $table->json('media_inventory')->nullable()->after('redacted_links');
                }
                if (!Schema::hasColumn('email_post_sanitized_meta', 'source_subject')) {
                    $table->string('source_subject', 500)->nullable()->after('sender_email');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('email_post_sanitized_meta')) {
            Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
                $columnsToDrop = [];
                if (Schema::hasColumn('email_post_sanitized_meta', 'media_inventory')) {
                    $columnsToDrop[] = 'media_inventory';
                }
                if (Schema::hasColumn('email_post_sanitized_meta', 'source_subject')) {
                    $columnsToDrop[] = 'source_subject';
                }
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
