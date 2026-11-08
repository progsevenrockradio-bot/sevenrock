<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talent_subscriptions')) {
            return;
        }

        Schema::table('talent_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('talent_subscriptions', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->unique('talent_subscriptions_payment_reference_unique')->after('payment_id');
            }
            if (! Schema::hasColumn('talent_subscriptions', 'confirmed_by_user_id')) {
                $table->foreignId('confirmed_by_user_id')->nullable()->after('payment_reference')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('talent_subscriptions', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmed_by_user_id');
            }
            if (! Schema::hasColumn('talent_subscriptions', 'notes')) {
                $table->text('notes')->nullable()->after('confirmed_at');
            }
            if (! Schema::hasColumn('talent_subscriptions', 'idempotency_token')) {
                $table->string('idempotency_token', 64)->nullable()->unique()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('talent_subscriptions')) {
            return;
        }

        Schema::table('talent_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('talent_subscriptions', 'confirmed_by_user_id')) {
                $table->dropForeign(['confirmed_by_user_id']);
                $table->dropColumn(['confirmed_by_user_id']);
            }
            if (Schema::hasColumn('talent_subscriptions', 'idempotency_token')) {
                $table->dropUnique(['idempotency_token']);
                $table->dropColumn(['idempotency_token']);
            }
            if (Schema::hasColumn('talent_subscriptions', 'payment_reference')) {
                $table->dropUnique('talent_subscriptions_payment_reference_unique');
            }
            $cols = array_filter(['payment_reference', 'confirmed_at', 'notes'], fn ($c) => Schema::hasColumn('talent_subscriptions', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

