<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_post_sanitized_meta')) {
            return;
        }

        Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
            if (! Schema::hasColumn('email_post_sanitized_meta', 'image_candidate_url')) {
                $table->string('image_candidate_url', 1000)->nullable()->after('image_alt');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'image_recovery_status')) {
                $table->string('image_recovery_status', 40)->nullable()->after('image_candidate_url');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'image_recovery_attempts')) {
                $table->unsignedSmallInteger('image_recovery_attempts')->default(0)->after('image_recovery_status');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'image_recovery_last_error')) {
                $table->string('image_recovery_last_error', 500)->nullable()->after('image_recovery_attempts');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'image_recovery_next_retry_at')) {
                $table->timestamp('image_recovery_next_retry_at')->nullable()->after('image_recovery_last_error');
                $table->index(['image_recovery_status', 'image_recovery_next_retry_at'], 'epsm_img_recovery_idx');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'image_recovery_last_attempt_at')) {
                $table->timestamp('image_recovery_last_attempt_at')->nullable()->after('image_recovery_next_retry_at');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'editorial_date')) {
                $table->date('editorial_date')->nullable()->after('event_date');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'forced_as_draft')) {
                $table->boolean('forced_as_draft')->default(false)->after('is_embargo_active');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'manually_modified_at')) {
                $table->timestamp('manually_modified_at')->nullable()->after('forced_as_draft');
            }
            if (! Schema::hasColumn('email_post_sanitized_meta', 'dv_policy_snapshot')) {
                $table->json('dv_policy_snapshot')->nullable()->after('media_inventory');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_post_sanitized_meta')) {
            return;
        }

        Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
            try {
                $table->dropIndex('epsm_img_recovery_idx');
            } catch (\Throwable) {
            }

            $cols = [
                'image_candidate_url',
                'image_recovery_status',
                'image_recovery_attempts',
                'image_recovery_last_error',
                'image_recovery_next_retry_at',
                'image_recovery_last_attempt_at',
                'editorial_date',
                'forced_as_draft',
                'manually_modified_at',
                'dv_policy_snapshot',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('email_post_sanitized_meta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
