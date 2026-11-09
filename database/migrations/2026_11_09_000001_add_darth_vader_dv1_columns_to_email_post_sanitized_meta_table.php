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
                if (! Schema::hasColumn('email_post_sanitized_meta', 'batch_id')) {
                    $table->string('batch_id', 100)->nullable()->index()->after('source_message_id');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'element_id')) {
                    $table->string('element_id', 100)->nullable()->index()->after('batch_id');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'event_date')) {
                    $table->string('event_date', 50)->nullable()->after('musical_release_date');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'artist_name')) {
                    $table->string('artist_name', 255)->nullable()->after('event_date');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'sources')) {
                    $table->text('sources')->nullable()->after('artist_name');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'image_credit')) {
                    $table->string('image_credit', 255)->nullable()->after('sources');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'corrects_element_id')) {
                    $table->string('corrects_element_id', 100)->nullable()->index()->after('superseded_by_message_id');
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
                foreach (['batch_id', 'element_id', 'event_date', 'artist_name', 'sources', 'image_credit', 'corrects_element_id'] as $column) {
                    if (Schema::hasColumn('email_post_sanitized_meta', $column)) {
                        $columnsToDrop[] = $column;
                    }
                }
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
