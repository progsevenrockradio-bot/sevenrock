<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('band_contacts')) {
            Schema::table('band_contacts', function (Blueprint $table): void {
                foreach ([
                    'program_code' => fn () => $table->string('program_code', 12)->nullable()->after('radio_artist_id'),
                    'referral_source' => fn () => $table->string('referral_source', 20)->default('producer')->after('program_code'),
                    'image_specs_met' => fn () => $table->boolean('image_specs_met')->default(false)->after('status'),
                    'audio_specs_met' => fn () => $table->boolean('audio_specs_met')->default(false)->after('image_specs_met'),
                    'submission_deadline' => fn () => $table->timestamp('submission_deadline')->nullable()->after('audio_specs_met'),
                    'materials_received_at' => fn () => $table->timestamp('materials_received_at')->nullable()->after('submission_deadline'),
                    'materials_note' => fn () => $table->text('materials_note')->nullable()->after('materials_received_at'),
                    'backblaze_path' => fn () => $table->string('backblaze_path')->nullable()->after('materials_note'),
                ] as $column => $callback) {
                    if (! Schema::hasColumn('band_contacts', $column)) {
                        $callback();
                    }
                }

                $table->index(['program_code', 'status'], 'band_contacts_program_status_index');
            });
        }

        if (Schema::hasTable('outreach_campaigns')) {
            Schema::table('outreach_campaigns', function (Blueprint $table): void {
                if (! Schema::hasColumn('outreach_campaigns', 'program_code')) {
                    $table->string('program_code', 12)->nullable()->after('template_id');
                    $table->index('program_code', 'outreach_campaigns_program_code_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('band_contacts')) {
            Schema::table('band_contacts', function (Blueprint $table): void {
                if (Schema::hasColumn('band_contacts', 'program_code')) {
                    $table->dropIndex('band_contacts_program_status_index');
                    $table->dropColumn('program_code');
                }

                foreach (['referral_source', 'image_specs_met', 'audio_specs_met', 'submission_deadline', 'materials_received_at', 'materials_note', 'backblaze_path'] as $column) {
                    if (Schema::hasColumn('band_contacts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('outreach_campaigns') && Schema::hasColumn('outreach_campaigns', 'program_code')) {
            Schema::table('outreach_campaigns', function (Blueprint $table): void {
                $table->dropIndex('outreach_campaigns_program_code_index');
                $table->dropColumn('program_code');
            });
        }
    }
};
