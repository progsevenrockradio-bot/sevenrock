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
        if (Schema::hasTable('email_post_sanitized_meta')) {
            Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
                if (! Schema::hasColumn('email_post_sanitized_meta', 'approved_urls')) {
                    $table->json('approved_urls')->nullable()->after('sources');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_urls');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
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
                $columns = [];
                if (Schema::hasColumn('email_post_sanitized_meta', 'approved_by')) {
                    $columns[] = 'approved_by';
                }
                if (Schema::hasColumn('email_post_sanitized_meta', 'approved_at')) {
                    $columns[] = 'approved_at';
                }
                if (Schema::hasColumn('email_post_sanitized_meta', 'approved_urls')) {
                    $columns[] = 'approved_urls';
                }
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
