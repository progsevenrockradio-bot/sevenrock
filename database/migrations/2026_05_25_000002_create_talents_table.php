<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talents')) {
            return;
        }

        Schema::create('talents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('band_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('bio')->nullable();
            $table->string('logo')->nullable();
            $table->enum('plan', ['free', 'basic', 'pro', 'premium'])->default('free');
            $table->enum('subscription_status', ['active', 'cancelled', 'expired'])->default('active');
            $table->string('payment_customer_id')->nullable();
            $table->unsignedInteger('interacts')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talents');
    }
};
