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
        if (! Schema::hasTable('promoter_codes')) {
            Schema::create('promoter_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 32)->unique()->index();
                $table->string('owner_name');
                $table->string('owner_email')->index();
                $table->string('owner_type', 32)->default('conductor'); // conductor | persona | banda
                $table->unsignedInteger('max_bands')->nullable()->default(18);
                $table->foreignId('assigned_talent_id')->nullable()->constrained('talents')->nullOnDelete();
                $table->boolean('is_archived')->default(false)->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('talent_referrals')) {
            Schema::create('talent_referrals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('referrer_talent_id')->nullable()->constrained('talents')->nullOnDelete();
                $table->foreignId('promoter_code_id')->nullable()->constrained('promoter_codes')->nullOnDelete();
                $table->foreignId('referred_talent_id')->constrained('talents')->cascadeOnDelete();
                $table->string('code', 32)->index();
                $table->string('status', 20)->default('pending')->index(); // pending | active | paid
                $table->string('reward_applied')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('talent_referrals');
        Schema::dropIfExists('promoter_codes');
    }
};
