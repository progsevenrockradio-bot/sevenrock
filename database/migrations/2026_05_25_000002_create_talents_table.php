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
            $table->unsignedBigInteger('user_id');
            $table->string('band_name')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->text('bio')->nullable();
            $table->string('logo')->nullable();
            $table->enum('plan', ['free', 'basic', 'pro', 'premium'])->default('free');
            $table->enum('subscription_status', ['active', 'inactive', 'cancelled'])->default('inactive');
            $table->string('payment_customer_id')->nullable();
            $table->string('payment_provider')->nullable();
            $table->integer('interacts')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talents');
    }
};
