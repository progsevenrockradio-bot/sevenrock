<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talent_subscription_reminders')) {
            return;
        }

        Schema::create('talent_subscription_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->foreignId('talent_subscription_id')->nullable()->constrained('talent_subscriptions')->cascadeOnDelete();
            $table->string('stage', 50); // 7_days_before, 1_day_before, at_expiry, 3_days_before_purge
            $table->date('cycle_end_date');
            $table->timestamp('sent_at');
            $table->string('recipient_email');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['talent_id', 'stage', 'cycle_end_date'], 'talent_reminder_cycle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_subscription_reminders');
    }
};

