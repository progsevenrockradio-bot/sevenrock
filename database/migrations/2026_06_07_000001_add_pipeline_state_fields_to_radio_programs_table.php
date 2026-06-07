<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('radio_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('radio_programs', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('status_message');
            }

            if (! Schema::hasColumn('radio_programs', 'processing_finished_at')) {
                $table->timestamp('processing_finished_at')->nullable()->after('processing_started_at');
            }

            if (! Schema::hasColumn('radio_programs', 'radioboss_started_at')) {
                $table->timestamp('radioboss_started_at')->nullable()->after('radioboss_metadata');
            }

            if (! Schema::hasColumn('radio_programs', 'radioboss_finished_at')) {
                $table->timestamp('radioboss_finished_at')->nullable()->after('radioboss_started_at');
            }

            if (! Schema::hasColumn('radio_programs', 'archive_started_at')) {
                $table->timestamp('archive_started_at')->nullable()->after('archive_org_metadata');
            }

            if (! Schema::hasColumn('radio_programs', 'archive_finished_at')) {
                $table->timestamp('archive_finished_at')->nullable()->after('archive_started_at');
            }

            if (! Schema::hasColumn('radio_programs', 'radioboss_notification_sent_at')) {
                $table->timestamp('radioboss_notification_sent_at')->nullable()->after('radioboss_finished_at');
            }

            if (! Schema::hasColumn('radio_programs', 'archive_notification_sent_at')) {
                $table->timestamp('archive_notification_sent_at')->nullable()->after('radioboss_notification_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('radio_programs', function (Blueprint $table): void {
            foreach ([
                'processing_started_at',
                'processing_finished_at',
                'radioboss_started_at',
                'radioboss_finished_at',
                'archive_started_at',
                'archive_finished_at',
                'radioboss_notification_sent_at',
                'archive_notification_sent_at',
            ] as $column) {
                if (Schema::hasColumn('radio_programs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
