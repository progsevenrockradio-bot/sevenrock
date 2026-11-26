<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_programs') && ! Schema::hasColumn('master_programs', 'recognize_delivery_from_notification_email')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                $table->boolean('recognize_delivery_from_notification_email')
                    ->default(false)
                    ->after('email_notificacion');
            });
        }

        if (! Schema::hasTable('master_program_delivery_senders')) {
            Schema::create('master_program_delivery_senders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('master_program_id')
                    ->constrained('master_programs')
                    ->cascadeOnDelete();
                $table->string('email', 255);
                $table->string('label', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['master_program_id', 'email'], 'mp_delivery_senders_program_email_unique');
                $table->index(['email', 'is_active'], 'mp_delivery_senders_email_active_idx');
            });
        }

        if (! Schema::hasTable('external_program_deliveries')) {
            Schema::create('external_program_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('master_program_id')
                    ->nullable()
                    ->constrained('master_programs')
                    ->nullOnDelete();
                $table->string('message_id', 255)->nullable()->unique();
                $table->string('mailbox', 255)->nullable();
                $table->string('folder', 120)->nullable();
                $table->unsignedBigInteger('imap_uid')->nullable();
                $table->string('source_type', 32)->default('email');
                $table->string('sender_email', 255)->nullable();
                $table->string('sender_name', 255)->nullable();
                $table->string('raw_from_email', 255)->nullable();
                $table->string('reply_to_email', 255)->nullable();
                $table->string('subject', 500)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->string('received_timezone', 64)->default('Europe/Madrid');
                $table->string('platform', 40)->default('other');
                $table->string('delivery_url', 1000);
                $table->json('additional_links')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('expiry_source', 40)->nullable();
                $table->string('auth_status', 40)->default('not_applicable');
                $table->string('auth_reason', 255)->nullable();
                $table->json('auth_details')->nullable();
                $table->string('identification_status', 40)->default('unidentified');
                $table->string('status', 32)->default('pending');
                $table->text('incident_reason')->nullable();
                $table->timestamp('snoozed_until')->nullable();
                $table->timestamp('initial_notified_at')->nullable();
                $table->string('telegram_status', 32)->default('pending');
                $table->text('telegram_last_error')->nullable();
                $table->timestamp('telegram_notified_at')->nullable();
                $table->timestamp('last_reminded_at')->nullable();
                $table->unsignedInteger('reminder_count')->default(0);
                $table->timestamp('expiry_alert_sent_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'snoozed_until'], 'ext_prog_deliv_status_snooze_idx');
                $table->index(['master_program_id', 'status'], 'ext_prog_deliv_program_status_idx');
                $table->index('received_at', 'ext_prog_deliv_received_at_idx');
            });
        }

        if (! Schema::hasTable('external_program_settings')) {
            Schema::create('external_program_settings', function (Blueprint $table): void {
                $table->string('key', 100)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_program_settings');
        Schema::dropIfExists('external_program_deliveries');
        Schema::dropIfExists('master_program_delivery_senders');

        if (Schema::hasTable('master_programs') && Schema::hasColumn('master_programs', 'recognize_delivery_from_notification_email')) {
            Schema::table('master_programs', function (Blueprint $table): void {
                $table->dropColumn('recognize_delivery_from_notification_email');
            });
        }
    }
};
