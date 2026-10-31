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
                if (! Schema::hasColumn('master_programs', 'storage_folder')) {
                    $table->string('storage_folder', 120)->nullable()->unique('master_programs_storage_folder_unique')->after('program_code');
                }
                if (! Schema::hasColumn('master_programs', 'editorial_mode')) {
                    $table->string('editorial_mode', 40)->default('mandatory_review')->after('activo');
                }
                if (! Schema::hasColumn('master_programs', 'editorial_guide')) {
                    $table->json('editorial_guide')->nullable()->after('editorial_mode');
                }
                if (! Schema::hasColumn('master_programs', 'autonomous_authorized_by')) {
                    $table->unsignedBigInteger('autonomous_authorized_by')->nullable()->after('editorial_guide');
                }
                if (! Schema::hasColumn('master_programs', 'autonomous_authorized_at')) {
                    $table->timestamp('autonomous_authorized_at')->nullable()->after('autonomous_authorized_by');
                }
            });
        }

        if (Schema::hasTable('theme_settings')) {
            Schema::table('theme_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('theme_settings', 'podcast_editorial_general_guide')) {
                    $table->json('podcast_editorial_general_guide')->nullable();
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'is_conductor')) {
                    $table->boolean('is_conductor')->default(false)->after('is_admin');
                }
            });
        }

        if (! Schema::hasTable('master_program_conductor')) {
            Schema::create('master_program_conductor', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('master_program_id')->constrained('master_programs')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('authorized_by')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamps();

                $table->unique(['master_program_id', 'user_id'], 'master_program_conductor_unique');
            });
        }

        if (! Schema::hasTable('conductor_upload_authorizations')) {
            Schema::create('conductor_upload_authorizations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('master_program_id')->constrained('master_programs')->cascadeOnDelete();
                $table->unsignedBigInteger('radio_program_id')->nullable()->index('conductor_upload_auth_episode_idx');
                $table->string('upload_token', 64)->unique('conductor_upload_auth_token_unique');
                $table->string('r2_key', 255)->unique('conductor_upload_auth_r2_key_unique');
                $table->string('expected_mime', 80)->default('audio/mpeg');
                $table->unsignedBigInteger('max_size_bytes')->default(524288000);
                $table->string('status', 32)->default('issued')->index('conductor_upload_auth_status_idx');
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->unsignedBigInteger('consumed_by_radio_program_id')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('radio_programs')) {
            Schema::table('radio_programs', function (Blueprint $table): void {
                if (! Schema::hasColumn('radio_programs', 'submitted_by_user_id')) {
                    $table->unsignedBigInteger('submitted_by_user_id')->nullable()->index('radio_programs_submitted_by_idx')->after('master_program_id');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_summary')) {
                    $table->text('editorial_summary')->nullable()->after('live_title');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_status')) {
                    $table->string('editorial_status', 40)->default('not_required')->index('radio_programs_editorial_status_idx')->after('delivery_status');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_version')) {
                    $table->unsignedInteger('editorial_version')->default(1)->after('editorial_status');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_version_token')) {
                    $table->string('editorial_version_token', 64)->nullable()->after('editorial_version');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_original_payload')) {
                    $table->json('editorial_original_payload')->nullable()->after('editorial_version_token');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_checks')) {
                    $table->json('editorial_checks')->nullable()->after('editorial_original_payload');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_ai_proposal')) {
                    $table->json('editorial_ai_proposal')->nullable()->after('editorial_checks');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_approved_at')) {
                    $table->timestamp('editorial_approved_at')->nullable()->after('editorial_ai_proposal');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_approved_by')) {
                    $table->unsignedBigInteger('editorial_approved_by')->nullable()->after('editorial_approved_at');
                }
                if (! Schema::hasColumn('radio_programs', 'editorial_approved_version')) {
                    $table->unsignedInteger('editorial_approved_version')->nullable()->after('editorial_approved_by');
                }
                if (! Schema::hasColumn('radio_programs', 'schedule_inclusion_status')) {
                    $table->string('schedule_inclusion_status', 40)->default('not_scheduled')->after('radioboss_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('radio_programs')) {
            Schema::table('radio_programs', function (Blueprint $table): void {
                $columns = [
                    'submitted_by_user_id',
                    'editorial_summary',
                    'editorial_status',
                    'editorial_version',
                    'editorial_version_token',
                    'editorial_original_payload',
                    'editorial_checks',
                    'editorial_ai_proposal',
                    'editorial_approved_at',
                    'editorial_approved_by',
                    'editorial_approved_version',
                    'schedule_inclusion_status',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('radio_programs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('conductor_upload_authorizations');
        Schema::dropIfExists('master_program_conductor');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_conductor')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('is_conductor');
            });
        }

        if (Schema::hasTable('theme_settings') && Schema::hasColumn('theme_settings', 'podcast_editorial_general_guide')) {
            Schema::table('theme_settings', function (Blueprint $table): void {
                $table->dropColumn('podcast_editorial_general_guide');
            });
        }

        if (Schema::hasTable('master_programs')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                $columns = [
                    'storage_folder',
                    'editorial_mode',
                    'editorial_guide',
                    'autonomous_authorized_by',
                    'autonomous_authorized_at',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('master_programs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

