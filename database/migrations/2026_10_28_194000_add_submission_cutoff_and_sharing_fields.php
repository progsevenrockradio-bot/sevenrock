<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_programs')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                if (! Schema::hasColumn('master_programs', 'submission_cutoff_minutes')) {
                    $table->unsignedInteger('submission_cutoff_minutes')
                        ->default(90)
                        ->after('duracion_minutos');
                }
            });
        }

        if (Schema::hasTable('radio_programs')) {
            Schema::table('radio_programs', function (Blueprint $table): void {
                if (! Schema::hasColumn('radio_programs', 'allow_public_download')) {
                    $table->boolean('allow_public_download')
                        ->default(false)
                        ->after('sync_archive_org');
                }
                if (! Schema::hasColumn('radio_programs', 'late_submission_authorized_by')) {
                    $table->unsignedBigInteger('late_submission_authorized_by')
                        ->nullable()
                        ->after('editorial_approved_version');
                }
                if (! Schema::hasColumn('radio_programs', 'late_submission_authorized_at')) {
                    $table->timestamp('late_submission_authorized_at')
                        ->nullable()
                        ->after('late_submission_authorized_by');
                }
                if (! Schema::hasColumn('radio_programs', 'late_submission_reason')) {
                    $table->text('late_submission_reason')
                        ->nullable()
                        ->after('late_submission_authorized_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('master_programs')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                if (Schema::hasColumn('master_programs', 'submission_cutoff_minutes')) {
                    $table->dropColumn('submission_cutoff_minutes');
                }
            });
        }

        if (Schema::hasTable('radio_programs')) {
            Schema::table('radio_programs', function (Blueprint $table): void {
                $cols = [
                    'allow_public_download',
                    'late_submission_authorized_by',
                    'late_submission_authorized_at',
                    'late_submission_reason',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('radio_programs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
