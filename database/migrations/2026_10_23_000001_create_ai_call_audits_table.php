<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_call_audits')) {
            Schema::create('ai_call_audits', function (Blueprint $table) {
                $table->id();
                $table->string('message_id', 255)->nullable()->index();
                $table->string('content_hash', 64)->index();
                $table->string('provider', 50); // gemini, openrouter, mock
                $table->string('model', 100);
                $table->string('stage', 50); // classification_extraction, editorial_writing, contact_info, efemerides, generic
                $table->unsignedInteger('prompt_tokens')->nullable();
                $table->unsignedInteger('completion_tokens')->nullable();
                $table->unsignedInteger('total_tokens')->nullable();
                $table->string('usage_source', 30)->default('estimated'); // api_reported, estimated, fallback
                $table->decimal('estimated_cost_eur', 10, 6)->default(0.000000);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->string('status', 30); // success, error, cached
                $table->text('error_message')->nullable();
                $table->unsignedSmallInteger('retry_count')->default(0);
                $table->timestamps();

                $table->index(['created_at', 'provider']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_audits');
    }
};
