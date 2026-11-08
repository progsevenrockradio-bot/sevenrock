<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2: Consentimiento opcional en maquetas y tabla de seguimiento
     * deduplicado por contacto y campaña para la invitación al Muro del Rock.
     */
    public function up(): void
    {
        Schema::table('track_submissions', function (Blueprint $table) {
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('marketing_opt_in_at')->nullable();
            $table->string('marketing_opt_in_source', 60)->nullable();
            $table->string('marketing_opt_in_version', 40)->nullable();
        });

        Schema::create('submission_muro_followups', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key', 60)->default('muro_rock_basic_v1');
            $table->string('email_normalized', 191);
            $table->string('contact_email', 255);
            $table->string('band_name', 255);

            $table->foreignId('track_submission_id')
                ->nullable()
                ->constrained('track_submissions')
                ->nullOnDelete();

            $table->foreignId('new_release_id')
                ->nullable()
                ->constrained('new_releases')
                ->nullOnDelete();

            // Estados: scheduled | paused | cancelled | skipped | sending | sent | failed | unknown
            $table->string('status', 24)->index();
            $table->string('status_reason', 80)->nullable();
            $table->text('status_detail')->nullable();

            $table->timestamp('first_published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(['email_normalized', 'campaign_key'], 'submission_muro_followups_email_campaign_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_muro_followups');

        Schema::table('track_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'marketing_opt_in',
                'marketing_opt_in_at',
                'marketing_opt_in_source',
                'marketing_opt_in_version',
            ]);
        });
    }
};

