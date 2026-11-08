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
        if (Schema::hasTable('promoter_codes')) {
            Schema::table('promoter_codes', function (Blueprint $table): void {
                if (! Schema::hasColumn('promoter_codes', 'campaign')) {
                    $table->string('campaign', 64)->default('pilot_2026')->after('owner_type');
                }
            });
        }

        if (Schema::hasTable('talent_referrals')) {
            Schema::table('talent_referrals', function (Blueprint $table): void {
                if (! Schema::hasColumn('talent_referrals', 'campaign')) {
                    $table->string('campaign', 64)->default('pilot_2026')->after('code');
                }
                if (! Schema::hasColumn('talent_referrals', 'first_paid_subscription_id')) {
                    $table->foreignId('first_paid_subscription_id')->nullable()->after('reward_applied')->constrained('talent_subscriptions')->nullOnDelete();
                }
                if (! Schema::hasColumn('talent_referrals', 'is_refunded')) {
                    $table->boolean('is_refunded')->default(false)->after('paid_at');
                }
                if (! Schema::hasColumn('talent_referrals', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable()->after('is_refunded');
                }
                if (! Schema::hasColumn('talent_referrals', 'refund_reason')) {
                    $table->text('refund_reason')->nullable()->after('refunded_at');
                }
            });
        }

        if (Schema::hasTable('talent_subscriptions')) {
            Schema::table('talent_subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('talent_subscriptions', 'is_courtesy')) {
                    $table->boolean('is_courtesy')->default(false)->after('status');
                }
                if (! Schema::hasColumn('talent_subscriptions', 'courtesy_type')) {
                    $table->string('courtesy_type', 32)->nullable()->after('is_courtesy'); // initial_collaborator | referral_reward | other
                }
                if (! Schema::hasColumn('talent_subscriptions', 'is_refunded')) {
                    $table->boolean('is_refunded')->default(false)->after('courtesy_type');
                }
                if (! Schema::hasColumn('talent_subscriptions', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable()->after('is_refunded');
                }
                if (! Schema::hasColumn('talent_subscriptions', 'refund_reason')) {
                    $table->text('refund_reason')->nullable()->after('refunded_at');
                }
                if (! Schema::hasColumn('talent_subscriptions', 'refunded_by_user_id')) {
                    $table->foreignId('refunded_by_user_id')->nullable()->after('refund_reason')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('talent_referral_rewards')) {
            Schema::create('talent_referral_rewards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('promoter_code_id')->nullable()->constrained('promoter_codes')->cascadeOnDelete();
                $table->foreignId('referrer_talent_id')->nullable()->constrained('talents')->nullOnDelete();
                $table->string('campaign', 64)->default('pilot_2026');
                $table->unsignedTinyInteger('reward_tier'); // 1 = 3 primeras bandas (+1 mes), 2 = 6 bandas (+1 mes)
                $table->json('qualifying_referral_ids')->nullable();
                $table->string('status', 32)->default('granted'); // granted | pending_pro_resolution | flagged_review | resolved
                $table->foreignId('granted_subscription_id')->nullable()->constrained('talent_subscriptions')->nullOnDelete();
                $table->unsignedInteger('granted_days')->default(30);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['promoter_code_id', 'campaign', 'reward_tier'], 'promoter_reward_tier_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('talent_referral_rewards');

        if (Schema::hasTable('talent_subscriptions')) {
            Schema::table('talent_subscriptions', function (Blueprint $table): void {
                $table->dropForeign(['refunded_by_user_id']);
                $table->dropColumn([
                    'is_courtesy',
                    'courtesy_type',
                    'is_refunded',
                    'refunded_at',
                    'refund_reason',
                    'refunded_by_user_id',
                ]);
            });
        }

        if (Schema::hasTable('talent_referrals')) {
            Schema::table('talent_referrals', function (Blueprint $table): void {
                $table->dropForeign(['first_paid_subscription_id']);
                $table->dropColumn([
                    'campaign',
                    'first_paid_subscription_id',
                    'is_refunded',
                    'refunded_at',
                    'refund_reason',
                ]);
            });
        }

        if (Schema::hasTable('promoter_codes')) {
            Schema::table('promoter_codes', function (Blueprint $table): void {
                $table->dropColumn('campaign');
            });
        }
    }
};

