<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talent_subscriptions')) {
            return;
        }

        Schema::create('talent_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('plan');
            $table->decimal('amount', 8, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_provider');
            $table->string('payment_id')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'cancelled', 'expired', 'pending'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_subscriptions');
    }
};
