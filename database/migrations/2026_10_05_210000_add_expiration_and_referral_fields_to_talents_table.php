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
        if (! Schema::hasTable('talents')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            if (! Schema::hasColumn('talents', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->index();
            }
            if (! Schema::hasColumn('talents', 'expires_grace_at')) {
                $table->timestamp('expires_grace_at')->nullable()->index();
            }
            if (! Schema::hasColumn('talents', 'referral_code')) {
                $table->string('referral_code', 16)->nullable()->unique();
            }
            if (! Schema::hasColumn('talents', 'referred_by_code')) {
                $table->string('referred_by_code', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('talents', 'facebook_screenshot')) {
                $table->string('facebook_screenshot')->nullable();
            }
            if (! Schema::hasColumn('talents', 'instagram_screenshot')) {
                $table->string('instagram_screenshot')->nullable();
            }
            if (! Schema::hasColumn('talents', 'country')) {
                $table->string('country')->nullable();
            }
            if (! Schema::hasColumn('talents', 'contact_phone')) {
                $table->string('contact_phone')->nullable();
            }
            if (! Schema::hasColumn('talents', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
            if (Schema::hasColumn('talents', 'subscription_status')) {
                $table->string('subscription_status', 32)->default('inactive')->change();
            }
            if (Schema::hasColumn('talents', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('talents')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            $columns = [
                'is_hidden',
                'expires_grace_at',
                'referral_code',
                'referred_by_code',
                'facebook_screenshot',
                'instagram_screenshot',
                'country',
                'contact_phone',
                'rejection_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('talents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
