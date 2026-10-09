<?php

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
        if (!Schema::hasTable('ai_usage_daily')) {
            Schema::create('ai_usage_daily', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('provider', 50)->default('total');
                $table->unsignedInteger('calls')->default(0);
                $table->unsignedInteger('filtered_emails')->default(0);
                $table->timestamps();

                $table->unique(['date', 'provider']);
                $table->index('date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_daily');
    }
};
