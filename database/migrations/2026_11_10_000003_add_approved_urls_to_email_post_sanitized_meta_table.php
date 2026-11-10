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
                if (Schema::hasColumn('email_post_sanitized_meta', 'approved_urls')) {
                    $table->dropColumn('approved_urls');
                }
            });
        }
    }
};

